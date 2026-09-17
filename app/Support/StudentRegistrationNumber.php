<?php

namespace App\Support;

use App\Models\Student;

/**
 * Student registration number generation.
 *
 * Shared by Admission Management and Student Management so both creation paths
 * allocate from a single sequence. The format is STD-{year}-{####}, with a
 * four-digit zero-padded counter for the year.
 *
 * Generation is run inside a transaction with the students table locked, so
 * two concurrent creations cannot derive the same number. The unique index on
 * registration_number is the final guard: if a collision still slips through,
 * the caller must retry with a freshly allocated number.
 *
 * This is the same concurrency strategy Teacher and AdmissionApplication use
 * for their own generated IDs.
 */
class StudentRegistrationNumber
{
    /**
     * Generate the next registration number for the current year.
     *
     * Must be called inside a transaction. The caller is responsible for
     * retrying on UniqueConstraintViolationException.
     */
    public static function next(): string
    {
        $year = date('Y');
        $prefix = "STD-{$year}-";

        $lastNumber = Student::where('registration_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('registration_number')
            ->value('registration_number');

        $nextNumber = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
