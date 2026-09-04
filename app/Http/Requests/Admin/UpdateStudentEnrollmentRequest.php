<?php

namespace App\Http\Requests\Admin;

/**
 * Edits one existing academic placement.
 *
 * The rules are the store rules with the row being edited excluded from the
 * "one per session per track" and "one active per track" checks, so saving
 * an enrollment without changing those fields does not conflict with itself.
 */
class UpdateStudentEnrollmentRequest extends StoreStudentEnrollmentRequest
{
    /**
     * The enrollment being edited.
     */
    protected function enrollmentId(): ?int
    {
        return $this->route('enrollment') ? (int) $this->route('enrollment') : null;
    }
}
