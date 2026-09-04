<?php

namespace Database\Seeders;

use App\Models\AcademicClass;
use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * Seeds the departments and classes the public admission form offers.
 *
 * Everything is matched on name first, so running this repeatedly is safe and
 * existing Master Data rows are left untouched.
 */
class AdmissionDepartmentClassSeeder extends Seeder
{
    /**
     * The classes each department offers, in display order.
     *
     * @var array<string, array{code: string, classes: array<int, string>}>
     */
    private array $structure = [
        'Hifz' => [
            'code' => 'HFZ',
            'classes' => ['Qaida', 'Nazra', 'Hifz', 'Gardan', 'Tajweed'],
        ],
        'School' => [
            'code' => 'SCH',
            'classes' => ['Montessori Section', 'Primary Section', 'Middle Section', '9th', '10th'],
        ],
        'Dars-e-Nizami' => [
            'code' => 'DEN',
            'classes' => ['سال اول', 'سال دوم', 'ادیب عربی', 'عالم عربی', 'فاضل عربی', 'دورہ حدیث فرسٹ ائیر'],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->structure as $departmentName => $definition) {
            $department = Department::firstOrCreate(
                ['name' => $departmentName],
                ['code' => $definition['code'], 'status' => true]
            );

            foreach ($definition['classes'] as $index => $className) {
                AcademicClass::firstOrCreate(
                    [
                        'department_id' => $department->id,
                        'name' => $className,
                    ],
                    [
                        'code' => $definition['code'] . '-' . ($index + 1),
                        'status' => true,
                    ]
                );
            }
        }
    }
}
