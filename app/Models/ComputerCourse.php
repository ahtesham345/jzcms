<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A course run by the Computer department.
 *
 * One course is what the institution runs today - the three-year course, six
 * semesters - and the table is shaped so a second one would need no schema
 * change rather than because a second one is expected. Everything that has
 * to find "the" course goes through current(), so adding one later is a
 * question about which is current, not a rewrite.
 *
 * The course is deliberately not tied to an academic session. It runs across
 * about three of them, and its semesters carry their own dates.
 */
class ComputerCourse extends Model
{
    use HasFactory;

    /**
     * The course the institution runs, as the Imam confirmed it.
     */
    public const STANDARD_NAME = '3-Year Computer Course';

    public const STANDARD_DURATION_YEARS = 3;

    public const STANDARD_SEMESTER_COUNT = 6;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'duration_years',
        'semester_count',
        'description',
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
            'duration_years' => 'integer',
            'semester_count' => 'integer',
            'status' => 'boolean',
        ];
    }

    /**
     * Get the semesters of this course, in the order they are taught.
     */
    public function semesters()
    {
        return $this->hasMany(ComputerCourseSemester::class)->orderBy('order');
    }

    /**
     * Get the course a Computer student is placed into.
     *
     * The active course, oldest first, so the answer does not move when a
     * second one is added later. Null when the Computer department has not
     * been set up yet, which every caller has to be able to handle: the
     * course is master data an admin creates, not something the code assumes
     * into existence.
     */
    public static function current(): ?self
    {
        return static::query()
            ->where('status', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * Get the semester a new Computer student starts in.
     *
     * The first stage of the course. Confirmed business rule: a student
     * admitted into the Computer programme begins at the first semester, and
     * an admin may move them afterwards.
     */
    public static function startingSemester(): ?ComputerCourseSemester
    {
        return static::current()?->semesters()->where('status', true)->first();
    }
}
