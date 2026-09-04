<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\Department;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeacherIdAndClassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Department $hifz;
    private Department $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->seed(\Database\Seeders\AdmissionDepartmentClassSeeder::class);

        $this->hifz = Department::where('name', 'Hifz')->firstOrFail();
        $this->school = Department::where('name', 'School')->firstOrFail();
    }

    private function classIn(Department $department, string $name): AcademicClass
    {
        return AcademicClass::where('department_id', $department->id)->where('name', $name)->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Muhammad Usman',
            'gender' => 'Male',
            'mobile_number' => '0300-1234567',
            'joining_date' => '2026-01-15',
            'teacher_status' => 'Active',
        ], $overrides);
    }

    /* ---------------------------------------------------------------- */
    /* Automatic teacher ID                                             */
    /* ---------------------------------------------------------------- */

    public function test_the_first_teacher_gets_the_expected_format(): void
    {
        $this->post(route('teachers.store'), $this->payload())->assertSessionHasNoErrors();

        $teacher = Teacher::sole();
        $year = date('Y');

        $this->assertSame("TCH-{$year}-0001", $teacher->teacher_id);
        $this->assertMatchesRegularExpression('/^TCH-\d{4}-\d{4}$/', $teacher->teacher_id);
    }

    public function test_the_year_comes_from_the_current_date(): void
    {
        $this->assertStringContainsString(date('Y'), Teacher::nextTeacherId());
        $this->assertSame('TCH-'.date('Y').'-0001', Teacher::nextTeacherId());
    }

    public function test_ids_are_sequential_and_unique(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post(route('teachers.store'), $this->payload(['full_name' => "Teacher {$i}"]))
                ->assertSessionHasNoErrors();
        }

        $year = date('Y');
        $ids = Teacher::orderBy('id')->pluck('teacher_id')->all();

        $this->assertSame([
            "TCH-{$year}-0001", "TCH-{$year}-0002", "TCH-{$year}-0003",
            "TCH-{$year}-0004", "TCH-{$year}-0005",
        ], $ids);

        $this->assertCount(5, array_unique($ids));
    }

    public function test_the_third_teacher_continues_the_sequence(): void
    {
        $year = date('Y');

        // Two teachers already exist.
        $this->post(route('teachers.store'), $this->payload(['full_name' => 'First Teacher']));
        $this->post(route('teachers.store'), $this->payload(['full_name' => 'Second Teacher']));

        $this->assertSame("TCH-{$year}-0001", Teacher::where('full_name', 'First Teacher')->sole()->teacher_id);
        $this->assertSame("TCH-{$year}-0002", Teacher::where('full_name', 'Second Teacher')->sole()->teacher_id);

        // The next one must continue from the highest existing ID.
        $this->assertSame("TCH-{$year}-0003", Teacher::nextTeacherId());

        $response = $this->post(route('teachers.store'), $this->payload(['full_name' => 'Third Teacher']));

        $third = Teacher::where('full_name', 'Third Teacher')->sole();

        // Database
        $this->assertSame("TCH-{$year}-0003", $third->teacher_id);

        // Success message
        $response->assertSessionHas('success', "Teacher created successfully with ID TCH-{$year}-0003.");

        // Profile
        $this->get(route('teachers.show', $third->id))
            ->assertOk()
            ->assertSee("TCH-{$year}-0003", false);

        // The first two are untouched.
        $this->assertSame("TCH-{$year}-0001", Teacher::where('full_name', 'First Teacher')->sole()->teacher_id);
        $this->assertSame("TCH-{$year}-0002", Teacher::where('full_name', 'Second Teacher')->sole()->teacher_id);
    }

    public function test_the_create_form_previews_the_next_available_id(): void
    {
        $year = date('Y');

        // With nothing yet created the form previews the first ID.
        $this->get(route('teachers.create'))
            ->assertOk()
            ->assertSee('Teacher ID', false)
            ->assertSee('value="TCH-'.$year.'-0001"', false);

        $this->post(route('teachers.store'), $this->payload(['full_name' => 'First Teacher']));
        $this->post(route('teachers.store'), $this->payload(['full_name' => 'Second Teacher']));

        // Drop the flash message from the previous creation, which also
        // contains an ID, so only the form field is under test.
        $this->flushSession();

        // With 0001 and 0002 taken, the form previews 0003.
        $this->get(route('teachers.create'))
            ->assertOk()
            ->assertSee('value="TCH-'.$year.'-0003"', false)
            ->assertDontSee('value="TCH-'.$year.'-0001"', false);

        // Creating that teacher moves the preview on to 0004.
        $this->post(route('teachers.store'), $this->payload(['full_name' => 'Third Teacher']));
        $this->assertSame("TCH-{$year}-0003", Teacher::where('full_name', 'Third Teacher')->sole()->teacher_id);

        $this->flushSession();

        $this->get(route('teachers.create'))
            ->assertOk()
            ->assertSee('value="TCH-'.$year.'-0004"', false)
            ->assertDontSee('value="TCH-'.$year.'-0003"', false);
    }

    public function test_the_previewed_id_is_not_submitted_and_the_backend_decides(): void
    {
        $year = date('Y');

        // The preview field carries no name, so nothing is posted...
        $this->get(route('teachers.create'))
            ->assertOk()
            ->assertDontSee('name="teacher_id"', false);

        // ...and a request forging the previewed value is still rejected.
        $this->post(route('teachers.store'), $this->payload(['teacher_id' => "TCH-{$year}-0001"]))
            ->assertSessionHasErrors('teacher_id');

        $this->assertSame(0, Teacher::count());
    }

    public function test_generation_runs_inside_a_transaction(): void
    {
        // The same protection as the APP/STD generators. lockForUpdate()
        // compiles to nothing on SQLite (it has no row locking), so the lock
        // itself cannot be asserted here; what is verifiable everywhere is
        // that the read and the insert share one transaction.
        $levelDuringInsert = null;
        Teacher::creating(function () use (&$levelDuringInsert) {
            $levelDuringInsert = DB::transactionLevel();
        });

        Teacher::createWithTeacherId($this->payload());

        $this->assertNotNull($levelDuringInsert);
        $this->assertGreaterThan(0, $levelDuringInsert, 'The teacher must be inserted inside a transaction');

        Teacher::flushEventListeners();
    }

    public function test_the_unique_index_is_the_final_guard(): void
    {
        $existing = Teacher::createWithTeacherId($this->payload());

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        $duplicate = new Teacher($this->payload());
        $duplicate->teacher_id = $existing->teacher_id;
        $duplicate->save();
    }

    public function test_teacher_id_cannot_be_supplied_on_create(): void
    {
        $this->post(route('teachers.store'), $this->payload(['teacher_id' => 'HACKED-001']))
            ->assertSessionHasErrors('teacher_id');

        $this->assertSame(0, Teacher::count());
    }

    public function test_teacher_id_cannot_be_changed_on_edit(): void
    {
        $teacher = Teacher::createWithTeacherId($this->payload());
        $originalId = $teacher->teacher_id;

        $this->put(route('teachers.update', $teacher->id), $this->payload([
            'teacher_id' => 'HACKED-001',
            'full_name' => 'Renamed',
        ]))->assertSessionHasErrors('teacher_id');

        $this->assertSame($originalId, $teacher->fresh()->teacher_id);

        // A legitimate edit leaves the ID untouched.
        $this->put(route('teachers.update', $teacher->id), $this->payload(['full_name' => 'Renamed']))
            ->assertSessionHasNoErrors();

        $this->assertSame($originalId, $teacher->fresh()->teacher_id);
        $this->assertSame('Renamed', $teacher->fresh()->full_name);
    }

    public function test_legacy_manual_ids_are_preserved_and_not_renumbered(): void
    {
        // A record created before automatic generation.
        $legacy = Teacher::createWithTeacherId($this->payload(['full_name' => 'Legacy Teacher']));
        $legacy->teacher_id = 'TCH-001';
        $legacy->save();

        // New teachers start their own sequence and leave the legacy id alone.
        $this->post(route('teachers.store'), $this->payload(['full_name' => 'New Teacher']))
            ->assertSessionHasNoErrors();

        $this->assertSame('TCH-001', $legacy->fresh()->teacher_id);
        $this->assertSame(
            'TCH-'.date('Y').'-0001',
            Teacher::where('full_name', 'New Teacher')->sole()->teacher_id
        );

        // The legacy record is still editable and viewable.
        $this->get(route('teachers.show', $legacy->id))->assertOk()->assertSee('TCH-001', false);
        $this->put(route('teachers.update', $legacy->id), $this->payload(['full_name' => 'Legacy Renamed']))
            ->assertSessionHasNoErrors();
        $this->assertSame('TCH-001', $legacy->fresh()->teacher_id);
    }

    /* ---------------------------------------------------------------- */
    /* Class assignment                                                 */
    /* ---------------------------------------------------------------- */

    public function test_a_teacher_can_be_assigned_one_class(): void
    {
        $class = $this->classIn($this->hifz, 'Nazra');

        $this->post(route('teachers.store'), $this->payload([
            'academic_class_ids' => [$class->id],
        ]))->assertSessionHasNoErrors();

        $teacher = Teacher::sole();
        $this->assertCount(1, $teacher->academicClasses);
        $this->assertSame('Nazra', $teacher->academicClasses->first()->name);
    }

    public function test_a_teacher_can_be_assigned_multiple_classes(): void
    {
        $ids = [
            $this->classIn($this->hifz, 'Nazra')->id,
            $this->classIn($this->hifz, 'Hifz')->id,
            $this->classIn($this->school, '9th')->id,
        ];

        $this->post(route('teachers.store'), $this->payload(['academic_class_ids' => $ids]))
            ->assertSessionHasNoErrors();

        $teacher = Teacher::sole();
        $this->assertCount(3, $teacher->academicClasses);
        $this->assertEqualsCanonicalizing($ids, $teacher->academicClasses->pluck('id')->all());
    }

    public function test_multiple_teachers_can_share_a_class(): void
    {
        $class = $this->classIn($this->school, 'Primary Section');

        $this->post(route('teachers.store'), $this->payload(['full_name' => 'First', 'academic_class_ids' => [$class->id]]));
        $this->post(route('teachers.store'), $this->payload(['full_name' => 'Second', 'academic_class_ids' => [$class->id]]));

        $this->assertCount(2, $class->fresh()->teachers);
        $this->assertEqualsCanonicalizing(
            ['First', 'Second'],
            $class->fresh()->teachers->pluck('full_name')->all()
        );
    }

    public function test_duplicate_assignments_are_prevented(): void
    {
        $class = $this->classIn($this->hifz, 'Nazra');

        // The same id sent twice results in a single pivot row.
        $this->post(route('teachers.store'), $this->payload([
            'academic_class_ids' => [$class->id, $class->id],
        ]))->assertSessionHasNoErrors();

        $teacher = Teacher::sole();
        $this->assertCount(1, $teacher->academicClasses);
        $this->assertSame(1, DB::table('teacher_class')->count());

        // The unique index rejects a duplicate inserted directly.
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('teacher_class')->insert([
            'teacher_id' => $teacher->id,
            'academic_class_id' => $class->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_editing_adds_and_removes_assignments(): void
    {
        $nazra = $this->classIn($this->hifz, 'Nazra');
        $hifz = $this->classIn($this->hifz, 'Hifz');
        $ninth = $this->classIn($this->school, '9th');

        $this->post(route('teachers.store'), $this->payload(['academic_class_ids' => [$nazra->id, $hifz->id]]));
        $teacher = Teacher::sole();
        $this->assertCount(2, $teacher->academicClasses);

        // Swap one out and add another.
        $this->put(route('teachers.update', $teacher->id), $this->payload([
            'academic_class_ids' => [$hifz->id, $ninth->id],
        ]))->assertSessionHasNoErrors();

        $teacher->refresh()->load('academicClasses');
        $this->assertEqualsCanonicalizing([$hifz->id, $ninth->id], $teacher->academicClasses->pluck('id')->all());
        $this->assertSame(2, DB::table('teacher_class')->count());

        // Removing all assignments works.
        $this->put(route('teachers.update', $teacher->id), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $teacher->fresh()->academicClasses);
        $this->assertSame(0, DB::table('teacher_class')->count());
    }

    public function test_a_teacher_without_any_class_works(): void
    {
        $this->post(route('teachers.store'), $this->payload())->assertSessionHasNoErrors();

        $teacher = Teacher::sole();
        $this->assertCount(0, $teacher->academicClasses);

        $this->get(route('teachers.show', $teacher->id))
            ->assertOk()
            ->assertSee('No classes assigned.', false);

        $this->get(route('teachers.index'))->assertOk();
    }

    public function test_inactive_and_invalid_classes_are_rejected(): void
    {
        $inactive = $this->classIn($this->hifz, 'Gardan');
        $inactive->update(['status' => false]);

        $this->post(route('teachers.store'), $this->payload(['academic_class_ids' => [$inactive->id]]))
            ->assertSessionHasErrors('academic_class_ids.0');

        // Nonexistent id.
        $this->post(route('teachers.store'), $this->payload(['academic_class_ids' => [999999]]))
            ->assertSessionHasErrors('academic_class_ids.0');

        // Non-integer id.
        $this->post(route('teachers.store'), $this->payload(['academic_class_ids' => ['abc']]))
            ->assertSessionHasErrors('academic_class_ids.0');

        $this->assertSame(0, Teacher::count());
        $this->assertSame(0, DB::table('teacher_class')->count());
    }

    public function test_inactive_classes_cannot_be_added_on_edit(): void
    {
        $teacher = Teacher::createWithTeacherId($this->payload());
        $inactive = $this->classIn($this->school, '10th');
        $inactive->update(['status' => false]);

        $this->put(route('teachers.update', $teacher->id), $this->payload([
            'academic_class_ids' => [$inactive->id],
        ]))->assertSessionHasErrors('academic_class_ids.0');

        $this->assertCount(0, $teacher->fresh()->academicClasses);
    }

    public function test_show_page_groups_assignments_by_department(): void
    {
        $ids = [
            $this->classIn($this->hifz, 'Nazra')->id,
            $this->classIn($this->hifz, 'Hifz')->id,
            $this->classIn($this->school, '9th')->id,
        ];

        $this->post(route('teachers.store'), $this->payload(['academic_class_ids' => $ids]));
        $teacher = Teacher::sole();

        $this->get(route('teachers.show', $teacher->id))
            ->assertOk()
            ->assertSee('Classes Taught', false)
            ->assertSee('Hifz Department', false)
            ->assertSee('School Department', false)
            ->assertSee('Nazra', false)
            ->assertSee('9th', false)
            ->assertDontSee('No classes assigned.', false);
    }

    public function test_forms_list_only_active_classes_grouped_by_department(): void
    {
        $inactive = $this->classIn($this->school, '10th');
        $inactive->update(['status' => false]);

        $response = $this->get(route('teachers.create'))->assertOk();
        $response->assertSee('Classes Taught', false);
        $response->assertSee('name="academic_class_ids[]"', false);
        $response->assertSee('<optgroup label="Hifz">', false);
        $response->assertSee('Nazra', false);
        $response->assertDontSee('>10th<', false);

        // Edit shows current assignments as selected.
        $nazra = $this->classIn($this->hifz, 'Nazra');
        $this->post(route('teachers.store'), $this->payload(['academic_class_ids' => [$nazra->id]]));
        $teacher = Teacher::sole();

        $this->get(route('teachers.edit', $teacher->id))
            ->assertOk()
            ->assertSee('value="'.$nazra->id.'" selected', false);
    }

    public function test_list_shows_assigned_classes(): void
    {
        $this->post(route('teachers.store'), $this->payload([
            'full_name' => 'Assigned Teacher',
            'academic_class_ids' => [$this->classIn($this->hifz, 'Nazra')->id],
        ]));

        $this->get(route('teachers.index'))
            ->assertOk()
            ->assertSee('Classes', false)
            ->assertSee('Nazra', false);
    }
}
