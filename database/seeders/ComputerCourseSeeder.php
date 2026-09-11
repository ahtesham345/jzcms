<?php

namespace Database\Seeders;

use App\Models\AcademicClass;
use App\Models\ComputerCourse;
use App\Models\ComputerCourseSemester;
use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * Seeds the Computer department and the course it runs.
 *
 * Computer became a department in its own right, so it needs the same master
 * data every other department has, plus the course structure that is its own:
 * three years divided into six semesters.
 *
 * Matched on name and order throughout, so running it twice changes nothing
 * and an admin's edits are never overwritten.
 *
 * Two things are deliberately left empty. The semester dates and the
 * curriculum are the admin's to enter - the institution knows when each
 * stage runs and what is taught in it, and seeding a guess would put
 * invented facts on the curriculum. An undated semester is one nobody has
 * dated yet, which is the truth.
 */
class ComputerCourseSeeder extends Seeder
{
    /**
     * The department the course belongs to.
     */
    public const DEPARTMENT = 'Computer';

    public const DEPARTMENT_CODE = 'CMP';

    /**
     * The single academic class the Computer department runs.
     *
     * The enrollment table requires an academic class on every placement, so
     * a Computer placement needs one to exist. It is the course itself, not
     * a stage of it: the six semesters are a progression the student moves
     * through, and turning them into six classes would make a second, rival
     * copy of the course structure in a table that means something else.
     *
     * One class, standing for "enrolled in the Computer course", with the
     * semester saying where in it they are.
     */
    public const COURSE_CLASS = '3-Year Computer Course';

    public const COURSE_CLASS_CODE = 'CMP-1';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $department = Department::firstOrCreate(
            ['name' => self::DEPARTMENT],
            ['code' => self::DEPARTMENT_CODE, 'status' => true]
        );

        AcademicClass::firstOrCreate(
            [
                'department_id' => $department->id,
                'name' => self::COURSE_CLASS,
            ],
            ['code' => self::COURSE_CLASS_CODE, 'status' => true]
        );

        $course = ComputerCourse::firstOrCreate(
            ['name' => ComputerCourse::STANDARD_NAME],
            [
                'duration_years' => ComputerCourse::STANDARD_DURATION_YEARS,
                'semester_count' => ComputerCourse::STANDARD_SEMESTER_COUNT,
                'status' => true,
            ]
        );

        foreach (ComputerCourseSemester::STANDARD_NAMES as $order => $name) {
            ComputerCourseSemester::firstOrCreate(
                [
                    'computer_course_id' => $course->id,
                    'order' => $order,
                ],
                [
                    'name' => $name,
                    // Dates and curriculum stay empty: the admin enters them.
                    'status' => true,
                ]
            );
        }
    }
}
