<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentAcademicEnrollment extends Model
{
    use HasFactory;

    /**
     * The selectable academic tracks.
     *
     * @var array<int, string>
     */
    public const ACADEMIC_TRACKS = [
        'Madrassa',
        'School',
    ];

    /**
     * The selectable enrollment statuses.
     *
     * @var array<int, string>
     */
    public const STATUSES = [
        'Active',
        'Completed',
        'Left',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_id',
        'academic_session_id',
        'academic_track',
        'department_id',
        'academic_class_id',
        'section_id',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Get the student this enrollment belongs to.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the academic session this enrollment belongs to.
     */
    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * Get the department this enrollment belongs to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the class this enrollment belongs to.
     */
    public function academicClass()
    {
        return $this->belongsTo(AcademicClass::class);
    }

    /**
     * Get the section this enrollment belongs to, if any.
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Get the results recorded against this enrollment.
     *
     * The inverse of the belongsTo on StudentResult. Only madrassa
     * enrollments ever carry one - the result module refuses to write
     * against a school enrollment - so on a school row this is simply
     * empty rather than meaningful.
     */
    public function studentResults()
    {
        return $this->hasMany(StudentResult::class);
    }

    /**
     * Determine whether this enrollment is the student's current one.
     */
    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    /**
     * Get the Tailwind badge classes for the enrollment's status.
     */
    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            'Active' => 'bg-green-100 text-green-800',
            'Completed' => 'bg-blue-100 text-blue-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
