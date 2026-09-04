<?php

namespace App\Support;

/**
 * The grade a percentage earns.
 *
 * One place, on purpose. The result module, the profile summary and the
 * tests all ask this class rather than each carrying their own ladder of
 * boundaries, so changing the institution's grading means changing one
 * thing.
 *
 * The boundaries themselves are not hard coded here either: they are read
 * from config('jzcms.grades'), which the project already ships with. That
 * config is the existing grading rule and this class does not invent a
 * second one beside it.
 *
 * Only the lower bound of each band is used. The configured bands are
 * written as whole numbers - 80 to 89 for A, 90 to 100 for A+ - which
 * leaves 89.5 belonging to no band at all. Reading the ladder downwards
 * from the highest minimum closes those gaps in the only way that keeps the
 * configured intent: 89.5 is not yet an A+, so it is an A.
 */
class GradeScale
{
    /**
     * The ladder used when the project has no grading config at all.
     *
     * Kept as a fallback rather than as the primary source: an installation
     * that has removed the config still has to be able to grade a paper.
     *
     * @var array<string, float>
     */
    public const FALLBACK = [
        'A+' => 90.0,
        'A' => 80.0,
        'B' => 70.0,
        'C' => 60.0,
        'D' => 50.0,
        'F' => 0.0,
    ];

    /**
     * Get the grading ladder as grade => minimum percentage, highest first.
     *
     * Anything the config cannot be read as a number is dropped rather than
     * coerced: a malformed band must not silently become a 0 that swallows
     * every percentage below it.
     *
     * @return array<string, float>
     */
    public static function ladder(): array
    {
        $configured = config('jzcms.grades');

        $ladder = [];

        if (is_array($configured)) {
            foreach ($configured as $grade => $band) {
                $min = is_array($band) ? ($band['min'] ?? null) : $band;

                if (! is_numeric($min)) {
                    continue;
                }

                $ladder[(string) $grade] = (float) $min;
            }
        }

        if ($ladder === []) {
            $ladder = self::FALLBACK;
        }

        arsort($ladder);

        return $ladder;
    }

    /**
     * Get the grade a percentage earns, or null when there is no percentage.
     *
     * Null in, null out: a result that has not been marked has no grade,
     * which is a different statement from a grade of F.
     *
     * The percentage is expected to be the stored, already rounded one, so
     * a paper that rounds up to 90.00 is graded as the 90.00 it is recorded
     * as rather than as the 89.995 it was before rounding.
     */
    public static function forPercentage(?float $percentage): ?string
    {
        if ($percentage === null) {
            return null;
        }

        foreach (self::ladder() as $grade => $minimum) {
            if ($percentage >= $minimum) {
                return $grade;
            }
        }

        // Only reachable if every configured band sits above the value,
        // which a sane ladder never does. The lowest band is returned
        // rather than null so a marked paper always carries a grade.
        return array_key_last(self::ladder());
    }

    /**
     * Get the grade that counts as a fail.
     *
     * The bottom band of the configured ladder, whatever it is called.
     * Read from the same ladder as everything else rather than written down
     * again as "F": an institution that renames or re-cuts its bottom band
     * must not end up with a pass/fail rule that still points at the old
     * one.
     *
     * Null only when there is no ladder at all, which cannot happen while
     * the fallback exists.
     */
    public static function failingGrade(): ?string
    {
        return array_key_last(self::ladder());
    }

    /**
     * Determine whether a grade is a pass.
     *
     * Every band except the bottom one. A grade that is not on the ladder -
     * a stored value from an older configuration, say - is not treated as a
     * pass, because nothing here can say that it was one.
     *
     * Null is not a pass and not a fail: it means the paper has not been
     * marked, which the report shows as Not Entered rather than counting
     * either way.
     */
    public static function isPassing(?string $grade): bool
    {
        if ($grade === null) {
            return false;
        }

        $ladder = self::ladder();

        return array_key_exists($grade, $ladder)
            && $grade !== array_key_last($ladder);
    }

    /**
     * Get the ladder as readable "A+ (90% and above)" style lines.
     *
     * For the interface, so the form can say what the boundaries are
     * without restating them.
     *
     * @return array<string, string>
     */
    public static function legend(): array
    {
        $legend = [];
        $previous = null;

        foreach (self::ladder() as $grade => $minimum) {
            $legend[$grade] = $previous === null
                ? self::trim($minimum).'% and above'
                : self::trim($minimum).'% to below '.self::trim($previous).'%';

            $previous = $minimum;
        }

        return $legend;
    }

    /**
     * Render a boundary without trailing zeros: 89.5 stays, 90.0 becomes 90.
     */
    private static function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
