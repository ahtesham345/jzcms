<?php

namespace App\Http\Requests\Admin;

use App\Models\MadrassaDailyRecord;
use Illuminate\Contracts\Validation\Validator;

/**
 * Corrects one existing daily record.
 *
 * The store rules, with two deliberate differences.
 *
 * The enrollment is fixed. It is read from the stored row rather than from
 * the form, so a hand-edited id cannot move a day's work onto another
 * student or onto the school side of a Hifz + School student. A request
 * that names a different one is rejected outright rather than ignored, so
 * the attempt is visible instead of silently absorbed.
 *
 * The enrollment no longer has to be active. A record made while a
 * placement was current must stay correctable after the student has been
 * promoted out of it - that is what keeps the history editable without
 * letting it be rewritten.
 */
class UpdateMadrassaDailyRecordRequest extends StoreMadrassaDailyRecordRequest
{
    /**
     * The record being edited, resolved from the route binding.
     */
    protected function existingRecord(): ?MadrassaDailyRecord
    {
        $record = $this->route('record');

        return $record instanceof MadrassaDailyRecord ? $record : null;
    }

    /**
     * The enrollment this record is written against.
     *
     * Always the stored one. Nothing in the request can change it, which
     * also means the inherited "enrollment belongs to this student" check
     * compares the submitted student against the record's real placement
     * rather than against another value from the same request.
     */
    protected function enrollmentId(): ?int
    {
        return $this->existingRecord()?->student_academic_enrollment_id;
    }

    /**
     * Editing a record does not require its enrollment to still be current.
     */
    protected function requiresActiveEnrollment(): bool
    {
        return false;
    }

    /**
     * Refuse an attempt to re-point the record before anything else runs.
     *
     * Registered ahead of the inherited checks on purpose: they stand down
     * when the enrollment is already in error, so this reads as the one
     * reason the save was refused rather than as the first of several.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $record = $this->existingRecord();

            if ($record === null) {
                return;
            }

            $submitted = $this->input('student_academic_enrollment_id');

            if ($submitted !== null && (int) $submitted !== (int) $record->student_academic_enrollment_id) {
                $validator->errors()->add(
                    'student_academic_enrollment_id',
                    'A daily record cannot be moved to a different academic enrollment.'
                );
            }
        });

        parent::withValidator($validator);
    }
}
