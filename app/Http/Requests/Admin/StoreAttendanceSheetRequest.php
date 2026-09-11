<?php

namespace App\Http\Requests\Admin;

use App\Models\StudentAcademicEnrollment;
use App\Models\StudentAttendance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Saves the marked cells of a monthly attendance sheet.
 *
 * The sheet arrives as one JSON field rather than as form inputs per cell.
 * A month of thirty students is several hundred cells, which would run past
 * PHP's max_input_vars long before a real class was transcribed.
 *
 * Nothing the browser sends about a student is trusted. The rows name
 * enrollments by id and nothing else; the track, the class group and
 * whether the enrollment is still active are all read back from the
 * database and checked here, in one query for the whole submission.
 */
class StoreAttendanceSheetRequest extends FormRequest
{
    /**
     * The submitted enrollments, resolved once, keyed by id.
     *
     * @var \Illuminate\Database\Eloquent\Collection<int, StudentAcademicEnrollment>|null
     */
    private $enrollments = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Unpack the sheet into rows the validator can walk.
     *
     * Anything that is not a JSON array of cells becomes an empty sheet,
     * which then fails as a whole rather than as a hundred confusing
     * per-row errors.
     */
    protected function prepareForValidation(): void
    {
        $decoded = json_decode((string) $this->input('sheet'), true);

        $this->merge([
            'attendance' => is_array($decoded) ? array_values($decoded) : [],
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The group the sheet was drawn for. Carried through the save so
            // every submitted row can be checked against it, and so the
            // redirect can reopen the same month.
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'academic_track' => ['required', Rule::in(StudentAcademicEnrollment::attendanceTracks())],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'academic_class_id' => ['required', 'integer', 'exists:academic_classes,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],

            // Any month, including months long past: this is a transcription
            // of paper registers, not a record of today.
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],

            'attendance_period' => [
                'required',
                Rule::in(StudentAttendance::ATTENDANCE_PERIODS),
                function ($attribute, $value, $fail) {
                    $track = $this->input('academic_track');

                    // A first pass against the selected track, so the error
                    // names the track the admin chose. The authoritative
                    // check is per enrollment, in withValidator() below.
                    if (in_array($track, StudentAcademicEnrollment::attendanceTracks(), true)
                        && ! StudentAttendance::periodAllowedForTrack($value, $track)) {
                        $allowed = implode(', ', StudentAttendance::periodsForTrack($track));
                        $fail("{$track} attendance is only recorded in the {$allowed} period.");
                    }
                },
            ],

            // Deliberately allowed to be empty. A month is digitised over
            // several sittings, so a save that changed nothing is a no-op,
            // not an error, and unmarked days never block it.
            'attendance' => ['present', 'array'],

            'attendance.*.student_academic_enrollment_id' => ['required', 'integer'],

            'attendance.*.attendance_date' => [
                'required',
                'date_format:Y-m-d',
                function ($attribute, $value, $fail) {
                    // Sunday is the weekly off day for both tracks, so no
                    // row may exist for it at all. The sheet renders it as
                    // OFF and unclickable; this is the rule that decides.
                    if ($offDay = StudentAttendance::offDayName($value)) {
                        $fail("Attendance cannot be recorded on a {$offDay} ({$value}). Sunday is the weekly off day.");
                    }

                    // The sheet may only write the month it was drawn for.
                    if (! StudentAttendance::isWithinMonth($value, (int) $this->input('year'), (int) $this->input('month'))) {
                        $fail("{$value} is outside the month this sheet was opened for.");
                    }
                },
            ],

            'attendance.*.status' => ['required', Rule::in(StudentAttendance::STATUSES)],

            // Required only for an absence. Free text so the administrator
            // can record the actual reason rather than the nearest option.
            'attendance.*.absence_reason' => [
                'required_if:attendance.*.status,'.StudentAttendance::STATUS_ABSENT,
                'nullable',
                'string',
                'max:255',
            ],

            'attendance.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Check every submitted row against the enrollment it names.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Only worth doing once the rows are structurally sound.
            if ($validator->errors()->hasAny(['attendance', 'attendance_period', 'month', 'year'])) {
                return;
            }

            $enrollments = $this->submittedEnrollments();
            $seen = [];

            foreach ($this->rows() as $index => $row) {
                $id = $row['student_academic_enrollment_id'] ?? null;
                $date = $row['attendance_date'] ?? null;

                if ($id === null || $date === null) {
                    continue;
                }

                $key = "attendance.{$index}.student_academic_enrollment_id";

                // One cell may only be marked once per submission: the
                // database would refuse the second write anyway.
                $cell = StudentAttendance::cellKey($id, $date);

                if (isset($seen[$cell])) {
                    $validator->errors()->add($key, 'The same student and day appears on this sheet more than once.');

                    continue;
                }

                $seen[$cell] = true;

                $enrollment = $enrollments->get((int) $id);

                if ($enrollment === null) {
                    $validator->errors()->add($key, 'The selected academic enrollment does not exist.');

                    continue;
                }

                if ($enrollment->status !== 'Active') {
                    $validator->errors()->add($key, "Attendance cannot be recorded against a {$enrollment->status} enrollment.");

                    continue;
                }

                if (! $this->belongsToSelectedGroup($enrollment)) {
                    $validator->errors()->add($key, 'That academic enrollment does not belong to the selected class group.');

                    continue;
                }

                // The one that decides: the track comes from the enrollment
                // row, so a browser claiming a school student is a madrassa
                // one still cannot record an evening period.
                if (! StudentAttendance::periodAllowedForTrack($this->input('attendance_period'), $enrollment->academic_track)) {
                    $allowed = implode(', ', StudentAttendance::periodsForTrack($enrollment->academic_track));

                    $validator->errors()->add(
                        'attendance_period',
                        "{$enrollment->academic_track} attendance is only recorded in the {$allowed} period."
                    );

                    return;
                }
            }
        });
    }

    /**
     * Get the submitted rows, skipping anything that is not a cell.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(): Collection
    {
        return collect((array) $this->input('attendance', []))
            ->filter(fn ($row) => is_array($row));
    }

    /**
     * Load every submitted enrollment in one query.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, StudentAcademicEnrollment>
     */
    private function submittedEnrollments()
    {
        if ($this->enrollments !== null) {
            return $this->enrollments;
        }

        // Distinct: a month of one student is many rows naming one id.
        $ids = $this->rows()
            ->pluck('student_academic_enrollment_id')
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        return $this->enrollments = StudentAcademicEnrollment::query()
            ->whereIn('id', $ids)
            ->get([
                'id',
                'academic_session_id',
                'academic_track',
                'department_id',
                'academic_class_id',
                'section_id',
                'status',
            ])
            ->keyBy('id');
    }

    /**
     * Determine whether an enrollment is part of the sheet's group.
     *
     * The section is only compared when one was chosen: a class may be run
     * without sections, and a class-wide sheet legitimately covers all of
     * them.
     */
    private function belongsToSelectedGroup(StudentAcademicEnrollment $enrollment): bool
    {
        $section = $this->input('section_id');

        return (int) $enrollment->academic_session_id === (int) $this->input('academic_session_id')
            && $enrollment->academic_track === $this->input('academic_track')
            && (int) $enrollment->department_id === (int) $this->input('department_id')
            && (int) $enrollment->academic_class_id === (int) $this->input('academic_class_id')
            && ($section === null || $section === '' || (int) $enrollment->section_id === (int) $section);
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attendance.*.status.required' => 'Every marked cell must be Present or Absent.',
            'attendance.*.absence_reason.required_if' => 'A reason is required for every absent student.',
        ];
    }

    /**
     * Get the custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'academic_session_id' => 'academic session',
            'academic_class_id' => 'class',
            'section_id' => 'section',
            'department_id' => 'department',
            'attendance_period' => 'attendance period',
        ];
    }
}
