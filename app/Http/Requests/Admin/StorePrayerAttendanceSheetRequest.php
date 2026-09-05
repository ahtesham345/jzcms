<?php

namespace App\Http\Requests\Admin;

use App\Models\StudentAcademicEnrollment;
use App\Models\StudentPrayerAttendance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Saves the changed cells of a monthly prayer sheet.
 *
 * The sheet arrives as one JSON field rather than as form inputs per cell.
 * A month of thirty students is over three thousand cells once the five
 * prayers are counted, which would run past PHP's max_input_vars long
 * before a real class was transcribed.
 *
 * Nothing the browser sends about a student is trusted. The rows name
 * enrollments by id and nothing else; the track, the student it belongs to,
 * the class group and whether the enrollment is still active are all read
 * back from the database and checked here, in one query for the whole
 * submission.
 *
 * The Madrassa-only rule is enforced from the enrollment row, never from
 * the form. A browser claiming a school enrollment is a madrassa one still
 * cannot record a prayer against it.
 */
class StorePrayerAttendanceSheetRequest extends FormRequest
{
    /**
     * The submitted enrollments, resolved once, keyed by id.
     *
     * @var \Illuminate\Database\Eloquent\Collection<int, StudentAcademicEnrollment>|null
     */
    private $enrollments = null;

    /**
     * Determine if the user is authorized to make this request.
     *
     * The route sits inside the authenticated admin area, which is what
     * decides who may reach it at all. Everything this class does beyond
     * that is about which rows the request may touch.
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
            'prayers' => is_array($decoded) ? array_values($decoded) : [],
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
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'academic_class_id' => ['required', 'integer', 'exists:academic_classes,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],

            // Any month, including months long past: this is a transcription
            // of a paper register, not a record of today.
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],

            // Deliberately allowed to be empty. A month is digitised over
            // several sittings, so a save that changed nothing is a no-op,
            // not an error, and unmarked prayers never block it.
            'prayers' => ['present', 'array'],

            'prayers.*.student_academic_enrollment_id' => ['required', 'integer'],

            // Carried on every row and checked against the enrollment below.
            // On its own it proves nothing; it is what makes "that
            // enrollment belongs to somebody else" an error the admin sees
            // rather than a prayer filed under the wrong student.
            'prayers.*.student_id' => ['required', 'integer'],

            'prayers.*.attendance_date' => [
                'required',
                'date_format:Y-m-d',
                function ($attribute, $value, $fail) {
                    // Sunday is off, so no row may exist for
                    // them at all. The sheet renders them as OFF and
                    // unclickable; this is the rule that decides.
                    if ($offDay = StudentPrayerAttendance::offDayName($value)) {
                        $fail("Prayer attendance cannot be recorded on a {$offDay} ({$value}). Sunday is the weekly off day.");
                    }

                    // The sheet may only write the month it was drawn for.
                    if (! StudentPrayerAttendance::isWithinMonth($value, (int) $this->input('year'), (int) $this->input('month'))) {
                        $fail("{$value} is outside the month this sheet was opened for.");
                    }
                },
            ],

            'prayers.*.prayer' => ['required', Rule::in(StudentPrayerAttendance::PRAYERS)],

            // Unmarked is an instruction to remove whatever is on file, not
            // a value the column can store.
            'prayers.*.status' => ['required', Rule::in(StudentPrayerAttendance::SUBMITTABLE_STATUSES)],

            // Optional, unlike the academic register: a paper prayer sheet
            // often records only the mark. Free text so the administrator
            // can write what actually happened.
            'prayers.*.absence_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Check every submitted row against the enrollment it names.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Only worth doing once the rows are structurally sound;
            // otherwise every check below would fail for the same reason and
            // bury the one that matters.
            if ($validator->errors()->hasAny(['prayers', 'month', 'year'])) {
                return;
            }

            $enrollments = $this->submittedEnrollments();
            $seen = [];

            foreach ($this->rows() as $index => $row) {
                $id = $row['student_academic_enrollment_id'] ?? null;
                $date = $row['attendance_date'] ?? null;
                $prayer = $row['prayer'] ?? null;

                if ($id === null || $date === null || $prayer === null) {
                    continue;
                }

                $key = "prayers.{$index}.student_academic_enrollment_id";

                // One cell may only be marked once per submission: the
                // database would refuse the second write anyway.
                $cell = StudentPrayerAttendance::cellKey($id, $date, $prayer);

                if (isset($seen[$cell])) {
                    $validator->errors()->add($key, 'The same student, day and prayer appears on this sheet more than once.');

                    continue;
                }

                $seen[$cell] = true;

                $enrollment = $enrollments->get((int) $id);

                if ($enrollment === null) {
                    $validator->errors()->add($key, 'The selected academic enrollment does not exist.');

                    continue;
                }

                // The one that decides. The track is read from the
                // enrollment row, so a school enrollment can never carry a
                // prayer however the request describes it.
                if ($enrollment->academic_track !== StudentPrayerAttendance::ACADEMIC_TRACK) {
                    $validator->errors()->add(
                        $key,
                        'Prayer attendance is only recorded for Madrassa enrollments.'
                    );

                    continue;
                }

                if ((int) $enrollment->student_id !== (int) ($row['student_id'] ?? 0)) {
                    $validator->errors()->add($key, 'That academic enrollment does not belong to the selected student.');

                    continue;
                }

                if ($enrollment->status !== 'Active') {
                    $validator->errors()->add($key, "Prayer attendance cannot be recorded against a {$enrollment->status} enrollment.");

                    continue;
                }

                if (! $this->belongsToSelectedGroup($enrollment)) {
                    $validator->errors()->add($key, 'That academic enrollment does not belong to the selected class group.');
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
        return collect((array) $this->input('prayers', []))
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
                'student_id',
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
            'prayers.*.prayer.in' => 'Prayer attendance can only be recorded for Fajr, Zuhr, Asr, Maghrib or Isha.',
            'prayers.*.status.in' => 'Every marked prayer must be Present, Absent or Unmarked.',
            'prayers.*.status.required' => 'Every marked prayer must be Present, Absent or Unmarked.',
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
        ];
    }
}
