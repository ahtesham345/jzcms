<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The instructions a guardian agrees to for one department.
 *
 * Stored as the admin typed them: one instruction per line, in a single
 * block, so the Urdu and the line breaks come back exactly as they went in.
 * The splitting into lines happens here rather than in a view or a
 * controller, so every reader gets the same list from the same rule.
 *
 * Only the items live here. The heading and the agreement sentence are the
 * institution's own, identical whatever the student studies, and stay in
 * config/admission_instructions.php where they already were.
 */
class DepartmentTerm extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'department_id',
        'items',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    /**
     * Get the department these instructions belong to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the instructions as a list, one per line.
     *
     * @return array<int, string>
     */
    public function itemLines(): array
    {
        return self::splitLines($this->items);
    }

    /**
     * Split a block of text into instruction lines.
     *
     * Blank lines are dropped rather than kept as empty instructions, and
     * carriage returns go with them: a browser posts CRLF and the list must
     * not depend on which one it was. The line's own text is otherwise left
     * alone - Urdu punctuation is content, not whitespace.
     *
     * @return array<int, string>
     */
    public static function splitLines(?string $block): array
    {
        if ($block === null || trim($block) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(
                fn (string $line) => trim($line),
                preg_split('/\R/u', $block) ?: []
            ),
            fn (string $line) => $line !== ''
        ));
    }

    /**
     * Join instruction lines back into a block for a textarea.
     *
     * @param  array<int, string>  $lines
     */
    public static function joinLines(array $lines): string
    {
        return implode("\n", $lines);
    }
}
