<?php

namespace App\Http\Requests\Admin;

use App\Models\MadrassaDailyRecord;
use App\Models\StudentAcademicEnrollment;
use App\Models\Teacher;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Records one madrassa student's work for one day.
 *
 * Nothing the browser says about the student is trusted. The form names an
 * enrollment by id; the track, the student it belongs to, whether it is
 * still active and which programme's fields apply are all read back from
 * the database here. The dependent selects on the page narrow what an
 * administrator sees, they do not decide what is accepted.
 *
 * The record type is never taken from the request either. It is derived
 * from the enrollment's student, and a request that names a different one
 * is rejected rather than quietly overridden, so a mismatch is visible
 * instead of silent.
 */
class StoreMadrassaDailyRecordRequest extends FormRequest
{
    /**
     * The enrollment named by this request, resolved once.
     */
    private ?StudentAcademicEnrollment $resolvedEnrollment = null;

    /**
     * Whether the enrollment lookup has already been attempted.
     *
     * Kept separately from the value: "not found" is a legitimate result
     * and must not send the query round again on every check below.
     */
    private bool $enrollmentResolved = false;

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
     * The record being edited, or null when one is being created.
     */
    protected function existingRecord(): ?MadrassaDailyRecord
    {
        return null;
    }

    /**
     * The id of the record being edited, if any.
     *
     * Used to exclude the row from the "one record per student per day"
     * check, so re-saving an unchanged date does not conflict with itself.
     */
    protected function recordId(): ?int
    {
        return $this->existingRecord()?->id;
    }

    /**
     * The enrollment this record is being written against.
     *
     * On a create that is whatever the form named; the checks below decide
     * whether it may be used.
     */
    protected function enrollmentId(): ?int
    {
        $id = $this->input('student_academic_enrollment_id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Whether the enrollment must still be active to be written against.
     *
     * True when creating: a new day's work only belongs to a placement the
     * student currently holds. Editing relaxes it, because a record made
     * under a then-active enrollment must stay correctable after the
     * student has been promoted out of it.
     */
    protected function requiresActiveEnrollment(): bool
    {
        return true;
    }

    /**
     * Load the enrollment named by this request, with its student.
     *
     * The student comes along because the programme, and therefore which
     * set of fields applies, is read from it.
     */
    protected function enrollment(): ?StudentAcademicEnrollment
    {
        if ($this->enrollmentResolved) {
            return $this->resolvedEnrollment;
        }

        $this->enrollmentResolved = true;

        $id = $this->enrollmentId();

        return $this->resolvedEnrollment = $id === null
            ? null
            : StudentAcademicEnrollment::with('student')->find($id);
    }

    /**
     * The record type this request may write.
     *
     * Derived from the enrollment's student, never from the form. Null when
     * the enrollment is missing, is not a madrassa placement, or belongs to
     * a programme that has no daily record at all.
     */
    public function resolvedRecordType(): ?string
    {
        return MadrassaDailyRecord::recordTypeForEnrollment($this->enrollment());
    }

    /**
     * Reduce blank inputs to null before anything is checked.
     *
     * An untouched text box arrives as an empty string. Left alone it
     * would count as a filled field for the "record something" rule below
     * and would be stored as '' rather than as nothing.
     */
    protected function prepareForValidation(): void
    {
        $fields = array_merge(
            ...array_values(MadrassaDailyRecord::WORK_FIELDS_BY_TYPE)
        );

        $cleaned = [];

        foreach (array_merge($fields, ['remarks', 'teacher_id']) as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $cleaned[$field] = trim($value) === '' ? null : trim($value);
        }

        if ($cleaned !== []) {
            $this->merge($cleaned);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            // Carried on the form and checked against the enrollment below.
            // On its own it proves nothing; it is what makes "this
            // enrollment belongs to somebody else" an error the admin sees
            // rather than a record filed under the wrong student.
            'student_id' => ['required', 'integer', 'exists:students,id'],

            'student_academic_enrollment_id' => [
                'required',
                'integer',
                'exists:student_academic_enrollments,id',
            ],

            'record_date' => [
                'required',
                'date_format:Y-m-d',

                // One record per student per day. The enrollment stands for
                // the student and the track together, so this is where a
                // second record is turned into "edit the first one". The
                // unique index is the final guard behind it.
                Rule::unique('madrassa_daily_records', 'record_date')
                    ->where('student_academic_enrollment_id', $this->enrollmentId())
                    ->ignore($this->recordId()),

                function ($attribute, $value, $fail) {
                    // Saturday and Sunday are off across the institution,
                    // so there is no day's work to record on them.
                    if ($offDay = MadrassaDailyRecord::offDayName($value)) {
                        $fail("A daily record cannot be created on a {$offDay} ({$value}). Saturday and Sunday are off days.");
                    }
                },
            ],

            // Optional, as agreed for this chunk: the project has teacher
            // records and a teacher-to-class assignment, but nothing that
            // says which teacher took a given lesson on a given day.
            //
            // Bailing so a non-numeric value is reported once rather than
            // by both the type rule and the lookup below it.
            'teacher_id' => [
                'bail',
                'nullable',
                'integer',
                function ($attribute, $value, $fail) {
                    // A record that already names a teacher keeps them even
                    // after they leave. Choosing one is what is restricted
                    // to current staff; re-saving a lesson that happened is
                    // not, and must not force a reassignment.
                    $existing = $this->existingRecord()?->teacher_id;

                    if ($existing !== null && (int) $value === (int) $existing) {
                        return;
                    }

                    $onStaff = Teacher::whereKey($value)
                        ->where('teacher_status', 'Active')
                        ->exists();

                    if (! $onStaff) {
                        $fail('The selected teacher is inactive or does not exist.');
                    }
                },
            ],

            'remarks' => ['nullable', 'string', 'max:2000'],
        ];

        // Every work column is accepted structurally; which of them may
        // actually carry a value is decided per record type in
        // withValidator(), where the enrollment is known.
        foreach (array_merge(...array_values(MadrassaDailyRecord::WORK_FIELDS_BY_TYPE)) as $field) {
            $rules[$field] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * Check the request against the enrollment it names.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Only worth doing once the ids and the date are structurally
            // sound; otherwise every check below would fail for the same
            // reason and bury the one that matters.
            if ($validator->errors()->hasAny(['student_academic_enrollment_id', 'student_id', 'record_date'])) {
                return;
            }

            $enrollment = $this->enrollment();

            if ($enrollment === null) {
                $validator->errors()->add(
                    'student_academic_enrollment_id',
                    'The selected academic enrollment does not exist.'
                );

                return;
            }

            if (! $this->enrollmentBelongsToStudent($validator, $enrollment)) {
                return;
            }

            if (! $this->enrollmentIsUsable($validator, $enrollment)) {
                return;
            }

            $recordType = $this->resolvedRecordType();

            if ($recordType === null) {
                $programme = $enrollment->student?->student_type ?? 'unknown';

                $validator->errors()->add(
                    'student_academic_enrollment_id',
                    "A daily academic record is not kept for a {$programme} student."
                );

                return;
            }

            $this->checkDateAgainstEnrollment($validator, $enrollment);
            $this->checkWorkFields($validator, $recordType);
        });
    }

    /**
     * Refuse an enrollment that belongs to somebody else.
     *
     * The whole point of carrying student_id on the form: without this an
     * edited id in the request would file one student's day under another
     * student's placement.
     */
    private function enrollmentBelongsToStudent(Validator $validator, StudentAcademicEnrollment $enrollment): bool
    {
        if ((int) $enrollment->student_id === (int) $this->input('student_id')) {
            return true;
        }

        $validator->errors()->add(
            'student_academic_enrollment_id',
            'That academic enrollment does not belong to the selected student.'
        );

        return false;
    }

    /**
     * Refuse an enrollment that is not a placement this module covers.
     *
     * The track is the one that matters most. A Hifz + School student holds
     * a school enrollment too, and it must never be the one a daily record
     * is filed against.
     */
    private function enrollmentIsUsable(Validator $validator, StudentAcademicEnrollment $enrollment): bool
    {
        if ($enrollment->academic_track !== MadrassaDailyRecord::ACADEMIC_TRACK) {
            $validator->errors()->add(
                'student_academic_enrollment_id',
                'A daily academic record can only be recorded against a Madrassa enrollment.'
            );

            return false;
        }

        if ($this->requiresActiveEnrollment() && $enrollment->status !== 'Active') {
            $validator->errors()->add(
                'student_academic_enrollment_id',
                "A daily record cannot be created against a {$enrollment->status} enrollment."
            );

            return false;
        }

        return true;
    }

    /**
     * Keep the date inside the placement it is being recorded against.
     *
     * A record before the enrollment started, or after it ended, would
     * claim the student was somewhere the academic history says they were
     * not.
     */
    private function checkDateAgainstEnrollment(Validator $validator, StudentAcademicEnrollment $enrollment): void
    {
        if (MadrassaDailyRecord::isWithinEnrollmentPeriod($this->input('record_date'), $enrollment)) {
            return;
        }

        $start = $enrollment->start_date?->format('d M, Y');
        $end = $enrollment->end_date?->format('d M, Y');

        $validator->errors()->add(
            'record_date',
            $end === null
                ? "This enrollment began on {$start}. A daily record cannot be dated before that."
                : "This enrollment ran from {$start} to {$end}. A daily record must fall inside that period."
        );
    }

    /**
     * Keep each programme to its own fields, and make the day say something.
     *
     * The two halves matter for different reasons. Refusing the other
     * programme's fields is what stops a Dars-e-Nizami record from being
     * given a Sabaq through a hand-edited request; requiring one of its own
     * is what stops an empty row from being filed as a day's work.
     */
    private function checkWorkFields(Validator $validator, string $recordType): void
    {
        foreach (MadrassaDailyRecord::foreignWorkFieldsFor($recordType) as $field) {
            if ($this->filled($field)) {
                $label = MadrassaDailyRecord::WORK_FIELD_LABELS[$field] ?? $field;

                $validator->errors()->add(
                    $field,
                    "{$label} is not part of a {$recordType} daily record."
                );
            }
        }

        $own = MadrassaDailyRecord::workFieldsFor($recordType);

        foreach ($own as $field) {
            if ($this->filled($field)) {
                return;
            }
        }

        $validator->errors()->add(
            $own[0],
            $recordType === MadrassaDailyRecord::TYPE_HIFZ
                ? 'Record at least one part of the day: Sabaq, Sabqi, Manzil or the next Sabaq.'
                : 'Record at least one part of the day: subject or book, the lesson, the topic covered, revision or the next lesson.'
        );
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'record_date.unique' => 'This student already has a daily record for that date. Edit the existing record instead of creating another one.',
            'record_date.date_format' => 'Enter the record date as a valid calendar date.',
            'student_academic_enrollment_id.exists' => 'The selected academic enrollment does not exist.',
        ];
    }

    /**
     * Get the custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge(
            MadrassaDailyRecord::WORK_FIELD_LABELS,
            [
                'student_academic_enrollment_id' => 'academic enrollment',
                'student_id' => 'student',
                'record_date' => 'record date',
                'teacher_id' => 'teacher',
            ]
        );
    }
}
