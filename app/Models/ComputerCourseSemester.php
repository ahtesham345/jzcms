<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One stage of a Computer course.
 *
 * The semester is the Computer programme's unit of progress: it is what a
 * student is currently in, and what the curriculum is written against. It is
 * deliberately not an academic class. A class belongs to a department and is
 * what a Madrassa or School student is placed in for a session; a semester
 * belongs to a course, carries its own dates and its own syllabus, and runs
 * wherever in the calendar the admin says it does.
 *
 * The dates and the curriculum are the admin's to enter. Nothing here fills
 * them in: an undated semester is a semester nobody has dated yet.
 */
class ComputerCourseSemester extends Model
{
    use HasFactory;

    /**
     * The names the six stages are seeded with.
     *
     * The names are ordinary text an admin may change. Only the order is
     * structural - it is what makes the course a sequence - so it is the
     * order, not the name, that everything else reads.
     *
     * @var array<int, string>
     */
    public const STANDARD_NAMES = [
        1 => '1st Semester',
        2 => '2nd Semester',
        3 => '3rd Semester',
        4 => '4th Semester',
        5 => '5th Semester',
        6 => '6th Semester',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'computer_course_id',
        'order',
        'name',
        'start_date',
        'end_date',
        'curriculum',
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
            'order' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => 'boolean',
        ];
    }

    /**
     * Get the course this semester belongs to.
     */
    public function computerCourse()
    {
        return $this->belongsTo(ComputerCourse::class);
    }

    /**
     * Get the placements currently standing in this semester.
     *
     * Used to answer "is anybody in this stage" before it is deleted, so a
     * semester somebody is enrolled in is never quietly removed from under
     * them.
     */
    public function enrollments()
    {
        return $this->hasMany(StudentAcademicEnrollment::class);
    }

    /**
     * Determine whether any student placement points at this semester.
     */
    public function hasEnrolledStudents(): bool
    {
        return $this->enrollments()->exists();
    }

    /**
     * Get the dates as one line, or a dash when they are not set yet.
     */
    public function dateRange(string $format = 'd M, Y'): string
    {
        if ($this->start_date === null && $this->end_date === null) {
            return '—';
        }

        return trim(
            ($this->start_date?->format($format) ?? '—')
            .' – '
            .($this->end_date?->format($format) ?? '—')
        );
    }
}
