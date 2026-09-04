<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Muhammad Usman',
            'father_name' => 'Abdul Rahman',
            'date_of_birth' => '1990-05-12',
            'gender' => 'Male',
            'cnic_number' => '35201-1234567-1',
            'mobile_number' => '0300-1234567',
            'alternate_mobile' => '0321-7654321',
            'email' => 'usman@example.com',
            'address' => 'House 12, Street 4, Lahore',
            'qualification' => 'M.A. Islamic Studies',
            'specialization' => 'Tajweed',
            'joining_date' => '2026-01-15',
            'teacher_status' => 'Active',
            'notes' => 'Senior Qari.',
        ], $overrides);
    }

    private function teacher(array $overrides = []): Teacher
    {
        // A caller may pin a specific id to simulate a legacy record.
        $forcedId = $overrides['teacher_id'] ?? null;
        unset($overrides['teacher_id']);

        $teacher = Teacher::createWithTeacherId($this->payload($overrides));

        if ($forcedId !== null) {
            $teacher->teacher_id = $forcedId;
            $teacher->save();
        }

        return $teacher;
    }

    public function test_a_teacher_can_be_created(): void
    {
        $response = $this->post(route('teachers.store'), $this->payload());

        $response->assertRedirect(route('teachers.index'));
        $response->assertSessionHas('success');

        $teacher = Teacher::sole();
        $this->assertSame('TCH-'.date('Y').'-0001', $teacher->teacher_id);
        $this->assertSame('Muhammad Usman', $teacher->full_name);
        $this->assertSame('Abdul Rahman', $teacher->father_name);
        $this->assertSame('1990-05-12', $teacher->date_of_birth->format('Y-m-d'));
        $this->assertSame('Male', $teacher->gender);
        $this->assertSame('35201-1234567-1', $teacher->cnic_number);
        $this->assertSame('0300-1234567', $teacher->mobile_number);
        $this->assertSame('usman@example.com', $teacher->email);
        $this->assertSame('Tajweed', $teacher->specialization);
        $this->assertSame('2026-01-15', $teacher->joining_date->format('Y-m-d'));
        $this->assertSame('Active', $teacher->teacher_status);
        $this->assertNull($teacher->photo);
    }

    public function test_a_teacher_can_be_updated(): void
    {
        $teacher = $this->teacher();

        $this->put(route('teachers.update', $teacher->id), $this->payload([
            'full_name' => 'Muhammad Usman Updated',
            'teacher_status' => 'Inactive',
            'qualification' => 'PhD Islamic Studies',
        ]))->assertRedirect(route('teachers.index'))->assertSessionHas('success');

        $teacher->refresh();
        $this->assertSame('Muhammad Usman Updated', $teacher->full_name);
        $this->assertSame('Inactive', $teacher->teacher_status);
        $this->assertSame('PhD Islamic Studies', $teacher->qualification);
    }

    public function test_a_teacher_can_be_deleted(): void
    {
        $teacher = $this->teacher();

        $this->delete(route('teachers.destroy', $teacher->id))
            ->assertRedirect(route('teachers.index'))
            ->assertSessionHas('success');

        $this->assertSame(0, Teacher::count());
    }

    public function test_validation_rejects_invalid_input(): void
    {
        // Required fields missing.
        $this->post(route('teachers.store'), [])
            ->assertSessionHasErrors(['full_name', 'gender', 'mobile_number', 'joining_date', 'teacher_status']);

        // Invalid enum values, email and date.
        $this->post(route('teachers.store'), $this->payload([
            'gender' => 'Other',
            'teacher_status' => 'Retired',
            'email' => 'not-an-email',
            'joining_date' => 'not-a-date',
            'date_of_birth' => '2999-01-01',
        ]))->assertSessionHasErrors(['gender', 'teacher_status', 'email', 'joining_date', 'date_of_birth']);

        $this->assertSame(0, Teacher::count());
    }

    public function test_optional_fields_may_be_omitted(): void
    {
        $this->post(route('teachers.store'), [
            'full_name' => 'Minimal Teacher',
            'gender' => 'Female',
            'mobile_number' => '0300-0000000',
            'joining_date' => '2026-02-01',
            'teacher_status' => 'Active',
        ])->assertSessionHasNoErrors();

        $teacher = Teacher::sole();
        $this->assertNull($teacher->father_name);
        $this->assertNull($teacher->date_of_birth);
        $this->assertNull($teacher->cnic_number);
        $this->assertNull($teacher->email);
        $this->assertNull($teacher->photo);
    }

    public function test_photo_is_optional_and_stored_as_a_path(): void
    {
        $this->post(route('teachers.store'), $this->payload([
            'photo' => UploadedFile::fake()->image('usman.jpg', 400, 400),
        ]))->assertSessionHasNoErrors();

        $teacher = Teacher::sole();
        $this->assertNotNull($teacher->photo);
        $this->assertStringStartsWith('teachers/', $teacher->photo);
        Storage::disk('public')->assertExists($teacher->photo);
        $this->assertTrue($teacher->hasPhoto());
    }

    public function test_non_images_and_oversized_photos_are_rejected(): void
    {
        $rejected = [
            UploadedFile::fake()->create('malware.php', 10, 'application/x-php'),
            UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->image('too-big.jpg')->size(3000),
            UploadedFile::fake()->image('wrong.gif'),
        ];

        foreach ($rejected as $file) {
            $this->post(route('teachers.store'), $this->payload(['photo' => $file]))
                ->assertSessionHasErrors('photo');
        }

        $this->assertSame(0, Teacher::count());
    }

    public function test_replacing_a_photo_deletes_the_old_file(): void
    {
        $this->post(route('teachers.store'), $this->payload([
            'photo' => UploadedFile::fake()->image('first.jpg'),
        ]));

        $teacher = Teacher::sole();
        $originalPath = $teacher->photo;
        Storage::disk('public')->assertExists($originalPath);

        $this->put(route('teachers.update', $teacher->id), $this->payload([
            'photo' => UploadedFile::fake()->image('second.jpg'),
        ]))->assertSessionHasNoErrors();

        $teacher->refresh();
        $this->assertNotSame($originalPath, $teacher->photo);
        Storage::disk('public')->assertMissing($originalPath);
        Storage::disk('public')->assertExists($teacher->photo);
        $this->assertCount(1, Storage::disk('public')->files('teachers'));
    }

    public function test_updating_without_a_new_photo_keeps_the_existing_one(): void
    {
        $this->post(route('teachers.store'), $this->payload([
            'photo' => UploadedFile::fake()->image('keep.jpg'),
        ]));

        $teacher = Teacher::sole();
        $originalPath = $teacher->photo;

        $this->put(route('teachers.update', $teacher->id), $this->payload([
            'full_name' => 'Renamed Teacher',
        ]))->assertSessionHasNoErrors();

        $teacher->refresh();
        $this->assertSame($originalPath, $teacher->photo, 'The photo must survive an edit that does not replace it');
        Storage::disk('public')->assertExists($originalPath);
    }

    public function test_deleting_a_teacher_removes_the_photo_file(): void
    {
        $this->post(route('teachers.store'), $this->payload([
            'photo' => UploadedFile::fake()->image('gone.jpg'),
        ]));

        $teacher = Teacher::sole();
        $path = $teacher->photo;

        $this->delete(route('teachers.destroy', $teacher->id));

        Storage::disk('public')->assertMissing($path);
        $this->assertSame(0, Teacher::count());
    }

    public function test_index_searches_by_id_name_and_mobile(): void
    {
        $this->teacher(['teacher_id' => 'TCH-801', 'full_name' => 'Ahmed Ali', 'mobile_number' => '0300-1111111']);
        $this->teacher(['teacher_id' => 'TCH-802', 'full_name' => 'Bilal Khan', 'mobile_number' => '0345-2222222']);

        $this->get(route('teachers.index', ['search' => 'TCH-801']))
            ->assertOk()->assertSee('Ahmed Ali', false)->assertDontSee('Bilal Khan', false);

        $this->get(route('teachers.index', ['search' => 'Bilal']))
            ->assertOk()->assertSee('Bilal Khan', false)->assertDontSee('Ahmed Ali', false);

        $this->get(route('teachers.index', ['search' => '0345-2222222']))
            ->assertOk()->assertSee('Bilal Khan', false)->assertDontSee('Ahmed Ali', false);
    }

    public function test_index_filters_by_status_and_keeps_filters_across_pages(): void
    {
        $this->teacher(['full_name' => 'Active Teacher', 'teacher_status' => 'Active']);
        $this->teacher(['full_name' => 'Inactive Teacher', 'teacher_status' => 'Inactive']);

        $this->get(route('teachers.index', ['teacher_status' => 'Active']))
            ->assertOk()->assertSee('Active Teacher', false)->assertDontSee('Inactive Teacher', false);

        $this->get(route('teachers.index', ['teacher_status' => 'Inactive']))
            ->assertOk()->assertSee('Inactive Teacher', false)->assertDontSee('Active Teacher', false);

        // 15 matches over 10 per page: the filter survives pagination.
        foreach (range(1, 14) as $i) {
            $this->teacher(['full_name' => "Paged Teacher {$i}", 'teacher_status' => 'Inactive']);
        }

        $response = $this->get(route('teachers.index', ['teacher_status' => 'Inactive']))->assertOk();
        $this->assertStringContainsString('teacher_status=Inactive', $response->getContent());
        $this->assertStringContainsString('page=2', $response->getContent());

        $this->get(route('teachers.index', ['teacher_status' => 'Inactive', 'page' => 2]))
            ->assertOk()
            ->assertSee('Showing 11 to 15 of 15', false);
    }

    public function test_show_page_displays_every_section(): void
    {
        $teacher = $this->teacher(['teacher_id' => 'TCH-900']);

        $this->get(route('teachers.show', $teacher->id))
            ->assertOk()
            ->assertSee('Personal Information', false)
            ->assertSee('Contact Information', false)
            ->assertSee('Professional Information', false)
            ->assertSee('Additional Information', false)
            ->assertSee('TCH-900', false)
            ->assertSee('Muhammad Usman', false)
            ->assertSee('Abdul Rahman', false)
            ->assertSee('12 May, 1990', false)
            ->assertSee('35201-1234567-1', false)
            ->assertSee('0300-1234567', false)
            ->assertSee('usman@example.com', false)
            ->assertSee('M.A. Islamic Studies', false)
            ->assertSee('Tajweed', false)
            ->assertSee('15 Jan, 2026', false)
            ->assertSee('Senior Qari.', false);
    }

    public function test_create_and_edit_pages_render(): void
    {
        $teacher = $this->teacher(['teacher_id' => 'TCH-950']);

        $this->get(route('teachers.create'))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="full_name"', false)
            // The field is labelled and disabled; the ID itself is generated
            // on save, so there is no input to submit.
            ->assertSee('Teacher ID', false)
            // The next ID is previewed, but the field is not submitted.
            ->assertSee('value="TCH-'.date('Y').'-0001"', false)
            ->assertDontSee('name="teacher_id"', false);

        $this->get(route('teachers.edit', $teacher->id))
            ->assertOk()
            ->assertSee('value="TCH-950"', false)
            ->assertSee('value="Muhammad Usman"', false)
            ->assertSee('No photo uploaded yet.', false);
    }

    public function test_teacher_pages_require_authentication(): void
    {
        $teacher = $this->teacher();
        auth()->logout();

        $this->get(route('teachers.index'))->assertRedirect(route('login'));
        $this->get(route('teachers.create'))->assertRedirect(route('login'));
        $this->get(route('teachers.show', $teacher->id))->assertRedirect(route('login'));
        $this->post(route('teachers.store'), $this->payload())->assertRedirect(route('login'));
    }
}
