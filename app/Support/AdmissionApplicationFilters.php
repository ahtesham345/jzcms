<?php

namespace App\Support;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Department;
use Illuminate\Http\Request;

/**
 * The filters the admission listing is narrowed by.
 *
 * One reader, shared by the listing and by the documents printed from it.
 * The listing and the Passed Students notice therefore agree on what a
 * filter means by construction: neither reads the query string on its own
 * terms, and a filter added here reaches both at once.
 *
 * What each filter *does* to a query lives on the model, in
 * AdmissionApplication::scopeFilter(). This class only decides what was
 * asked for. The split matters: the model is the single definition of the
 * SQL, and this is the single definition of the request.
 *
 * Everything read here is validated against what actually exists - the
 * statuses, student types, genders and test results the model declares, and
 * the department and class ids the database holds. An unrecognised value is
 * dropped rather than passed on, so a hand-edited query string narrows to
 * nothing dangerous: it simply stops being a filter. Ids are additionally
 * checked in the request layer by the controllers that need a hard failure.
 */
class AdmissionApplicationFilters
{
    /**
     * Read the admission filters off a request.
     *
     * Missing keys are returned as null rather than omitted, so callers can
     * read every filter without checking whether it was sent.
     *
     * @return array<string, mixed>
     */
    public static function fromRequest(Request $request): array
    {
        return [
            'search' => self::text($request, 'search'),
            'status' => self::oneOf($request, 'status', AdmissionApplication::STATUSES),
            'student_type' => self::oneOf($request, 'student_type', AdmissionApplication::STUDENT_TYPES),
            'test_result' => self::oneOf($request, 'test_result', AdmissionApplication::TEST_RESULTS),
            'gender' => self::oneOf($request, 'gender', AdmissionApplication::GENDERS),
            'department_id' => self::id($request, 'department_id'),
            'academic_class_id' => self::id($request, 'academic_class_id'),
            // No default. The listing deliberately opens on every session
            // rather than snapping to the current one: applications filed
            // before the session was recorded carry none, and defaulting the
            // filter would hide all of them from the main admission screen.
            // The passed students notice makes the opposite choice, and makes
            // it for itself - a notice board sheet has to be about one year.
            'academic_session_id' => self::id($request, 'academic_session_id'),
        ];
    }

    /**
     * Get the option lists the filter form is built from.
     *
     * The classes are grouped by department so the class dropdown can be
     * narrowed to the chosen department in the browser, which is the pattern
     * the results, academic and attendance filters already use.
     *
     * @return array<string, mixed>
     */
    public static function options(): array
    {
        return [
            'academicSessions' => AcademicSession::where('status', true)
                ->orderByDesc('start_date')
                ->get(),
            'departments' => Department::where('status', true)->orderBy('name')->get(),
            'classesByDepartment' => AcademicClass::where('status', true)
                ->orderBy('name')
                ->get(['id', 'name', 'department_id'])
                ->groupBy('department_id')
                ->map(fn ($classes) => $classes->map->only(['id', 'name'])->values()),
        ];
    }

    /**
     * Read a free-text filter, trimmed, or null when it is empty.
     */
    private static function text(Request $request, string $key): ?string
    {
        if (! $request->filled($key)) {
            return null;
        }

        $value = trim((string) $request->input($key));

        return $value === '' ? null : $value;
    }

    /**
     * Read a filter that may only hold one of a fixed set of values.
     *
     * @param  array<int, string>  $allowed
     */
    private static function oneOf(Request $request, string $key, array $allowed): ?string
    {
        $value = $request->input($key);

        return in_array($value, $allowed, true) ? $value : null;
    }

    /**
     * Read a filter that names a record by id.
     *
     * Only a positive integer is a possible id, so anything else is dropped
     * here and never reaches a query.
     */
    private static function id(Request $request, string $key): ?int
    {
        $value = $request->input($key);

        if (! is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }
}
