<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DepartmentTerm;
use App\Support\AcademicPlacement;
use App\Support\StudentTerms;
use Illuminate\Database\Seeder;

/**
 * Gives every real department the instructions the system already shows.
 *
 * The wording does not change. Before this, one set of instructions was
 * shown to every guardian; after it, each department holds its own copy of
 * that same set, so the Imam can begin editing one department without
 * touching the others and without anybody having to retype seventeen lines
 * of Urdu.
 *
 * Only the departments the student type mapping actually places students in
 * are seeded, and they are matched by name through that mapping. A
 * department created by mistake - a combined "Dars-e-Nizami + Computer",
 * say - is not one of them and is deliberately left without instructions.
 *
 * firstOrCreate throughout, so running it again is safe and an edit the
 * Imam has already made is never overwritten.
 */
class DepartmentTermsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = DepartmentTerm::joinLines(StudentTerms::defaults());

        if (trim($defaults) === '') {
            return;
        }

        $departments = Department::query()
            ->whereIn('name', AcademicPlacement::requiredDepartmentNames())
            ->get();

        foreach ($departments as $department) {
            DepartmentTerm::firstOrCreate(
                ['department_id' => $department->id],
                ['items' => $defaults, 'status' => true]
            );
        }
    }
}
