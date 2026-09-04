<?php

namespace Tests\Feature;

use App\Models\ParentGuardian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParentManagementTest extends TestCase
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
            'full_name' => 'Abdul Rahman',
            'father_name' => 'Muhammad Yousaf',
            'gender' => 'Male',
            'cnic_number' => '35201-7654321-9',
            'mobile_number' => '0300-1234567',
            'alternate_mobile' => '0321-7654321',
            'email' => 'rahman@example.com',
            'address' => 'House 12, Street 4, Lahore',
            'occupation' => 'Shopkeeper',
            'parent_status' => 'Active',
            'notes' => 'Prefers evening calls.',
        ], $overrides);
    }

    private function parent(array $overrides = []): ParentGuardian
    {
        // A caller may pin a specific id to simulate a legacy record.
        $forcedId = $overrides['parent_id'] ?? null;
        unset($overrides['parent_id']);

        $parent = ParentGuardian::createWithParentId($this->payload($overrides));

        if ($forcedId !== null) {
            $parent->parent_id = $forcedId;
            $parent->save();
        }

        return $parent;
    }

    /* ---------------------------------------------------------------- */
    /* CRUD                                                             */
    /* ---------------------------------------------------------------- */

    public function test_a_parent_can_be_created(): void
    {
        $response = $this->post(route('parents.store'), $this->payload());

        $response->assertRedirect(route('parents.index'));
        $response->assertSessionHas('success');

        $parent = ParentGuardian::sole();
        $this->assertSame('PAR-'.date('Y').'-0001', $parent->parent_id);
        $this->assertSame('Abdul Rahman', $parent->full_name);
        $this->assertSame('Muhammad Yousaf', $parent->father_name);
        $this->assertSame('Male', $parent->gender);
        $this->assertSame('35201-7654321-9', $parent->cnic_number);
        $this->assertSame('0300-1234567', $parent->mobile_number);
        $this->assertSame('0321-7654321', $parent->alternate_mobile);
        $this->assertSame('rahman@example.com', $parent->email);
        $this->assertSame('Shopkeeper', $parent->occupation);
        $this->assertSame('Active', $parent->parent_status);
        $this->assertNull($parent->photo);
    }

    public function test_a_parent_can_be_updated(): void
    {
        $parent = $this->parent();

        $this->put(route('parents.update', $parent->id), $this->payload([
            'full_name' => 'Abdul Rahman Updated',
            'parent_status' => 'Inactive',
            'occupation' => 'Teacher',
        ]))->assertRedirect(route('parents.index'))->assertSessionHas('success');

        $parent->refresh();
        $this->assertSame('Abdul Rahman Updated', $parent->full_name);
        $this->assertSame('Inactive', $parent->parent_status);
        $this->assertSame('Teacher', $parent->occupation);
    }

    public function test_a_parent_can_be_deleted(): void
    {
        $parent = $this->parent();

        $this->delete(route('parents.destroy', $parent->id))
            ->assertRedirect(route('parents.index'))
            ->assertSessionHas('success');

        $this->assertSame(0, ParentGuardian::count());
    }

    public function test_create_and_edit_pages_render(): void
    {
        $parent = $this->parent(['parent_id' => 'PAR-950']);

        $this->get(route('parents.create'))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="full_name"', false)
            // The field is labelled and disabled; the ID itself is generated
            // on save, so there is no input to submit.
            ->assertSee('Parent ID', false)
            ->assertSee('value="PAR-'.date('Y').'-0001"', false)
            ->assertDontSee('name="parent_id"', false);

        $this->get(route('parents.edit', $parent->id))
            ->assertOk()
            ->assertSee('value="PAR-950"', false)
            ->assertSee('value="Abdul Rahman"', false)
            ->assertSee('No photo uploaded yet.', false);
    }

    public function test_parent_pages_require_authentication(): void
    {
        $parent = $this->parent();
        auth()->logout();

        $this->get(route('parents.index'))->assertRedirect(route('login'));
        $this->get(route('parents.create'))->assertRedirect(route('login'));
        $this->get(route('parents.show', $parent->id))->assertRedirect(route('login'));
        $this->post(route('parents.store'), $this->payload())->assertRedirect(route('login'));
    }

    /* ---------------------------------------------------------------- */
    /* Automatic parent ID                                              */
    /* ---------------------------------------------------------------- */

    public function test_the_first_parent_gets_the_expected_format(): void
    {
        $this->post(route('parents.store'), $this->payload())->assertSessionHasNoErrors();

        $parent = ParentGuardian::sole();
        $year = date('Y');

        $this->assertSame("PAR-{$year}-0001", $parent->parent_id);
        $this->assertMatchesRegularExpression('/^PAR-\d{4}-\d{4}$/', $parent->parent_id);
    }

    public function test_the_year_comes_from_the_current_date(): void
    {
        $this->assertStringContainsString(date('Y'), ParentGuardian::nextParentId());
        $this->assertSame('PAR-'.date('Y').'-0001', ParentGuardian::nextParentId());
    }

    public function test_ids_are_sequential_and_unique(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post(route('parents.store'), $this->payload(['full_name' => "Parent {$i}"]))
                ->assertSessionHasNoErrors();
        }

        $year = date('Y');
        $ids = ParentGuardian::orderBy('id')->pluck('parent_id')->all();

        $this->assertSame([
            "PAR-{$year}-0001", "PAR-{$year}-0002", "PAR-{$year}-0003",
            "PAR-{$year}-0004", "PAR-{$year}-0005",
        ], $ids);

        $this->assertCount(5, array_unique($ids));
    }

    public function test_the_create_form_previews_the_next_available_id(): void
    {
        $year = date('Y');

        $this->get(route('parents.create'))
            ->assertOk()
            ->assertSee('value="PAR-'.$year.'-0001"', false);

        $this->post(route('parents.store'), $this->payload(['full_name' => 'First Parent']));
        $this->post(route('parents.store'), $this->payload(['full_name' => 'Second Parent']));

        // Drop the flash message from the previous creation, which also
        // contains an ID, so only the form field is under test.
        $this->flushSession();

        $this->get(route('parents.create'))
            ->assertOk()
            ->assertSee('value="PAR-'.$year.'-0003"', false)
            ->assertDontSee('value="PAR-'.$year.'-0001"', false);
    }

    public function test_generation_runs_inside_a_transaction(): void
    {
        // lockForUpdate() compiles to nothing on SQLite (it has no row
        // locking), so the lock itself cannot be asserted here; what is
        // verifiable everywhere is that the read and the insert share one
        // transaction.
        $levelDuringInsert = null;
        ParentGuardian::creating(function () use (&$levelDuringInsert) {
            $levelDuringInsert = DB::transactionLevel();
        });

        ParentGuardian::createWithParentId($this->payload());

        $this->assertNotNull($levelDuringInsert);
        $this->assertGreaterThan(0, $levelDuringInsert, 'The parent must be inserted inside a transaction');

        ParentGuardian::flushEventListeners();
    }

    public function test_the_unique_index_is_the_final_guard(): void
    {
        $existing = ParentGuardian::createWithParentId($this->payload());

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        $duplicate = new ParentGuardian($this->payload());
        $duplicate->parent_id = $existing->parent_id;
        $duplicate->save();
    }

    public function test_parent_id_cannot_be_supplied_on_create(): void
    {
        $this->post(route('parents.store'), $this->payload(['parent_id' => 'HACKED-001']))
            ->assertSessionHasErrors('parent_id');

        $this->assertSame(0, ParentGuardian::count());
    }

    public function test_parent_id_cannot_be_changed_on_edit(): void
    {
        $parent = ParentGuardian::createWithParentId($this->payload());
        $originalId = $parent->parent_id;

        $this->put(route('parents.update', $parent->id), $this->payload([
            'parent_id' => 'HACKED-001',
            'full_name' => 'Renamed',
        ]))->assertSessionHasErrors('parent_id');

        $this->assertSame($originalId, $parent->fresh()->parent_id);

        // A legitimate edit leaves the ID untouched.
        $this->put(route('parents.update', $parent->id), $this->payload(['full_name' => 'Renamed']))
            ->assertSessionHasNoErrors();

        $this->assertSame($originalId, $parent->fresh()->parent_id);
        $this->assertSame('Renamed', $parent->fresh()->full_name);
    }

    public function test_existing_ids_are_never_renumbered(): void
    {
        // A record created before automatic generation.
        $legacy = $this->parent(['parent_id' => 'PAR-001', 'full_name' => 'Legacy Parent']);

        // New parents start their own sequence and leave the legacy id alone.
        $this->post(route('parents.store'), $this->payload(['full_name' => 'New Parent']))
            ->assertSessionHasNoErrors();

        $this->assertSame('PAR-001', $legacy->fresh()->parent_id);
        $this->assertSame(
            'PAR-'.date('Y').'-0001',
            ParentGuardian::where('full_name', 'New Parent')->sole()->parent_id
        );

        // The legacy record is still editable and viewable.
        $this->get(route('parents.show', $legacy->id))->assertOk()->assertSee('PAR-001', false);
        $this->put(route('parents.update', $legacy->id), $this->payload(['full_name' => 'Legacy Renamed']))
            ->assertSessionHasNoErrors();
        $this->assertSame('PAR-001', $legacy->fresh()->parent_id);
    }

    /* ---------------------------------------------------------------- */
    /* Validation                                                       */
    /* ---------------------------------------------------------------- */

    public function test_validation_rejects_invalid_input(): void
    {
        // Required fields missing.
        $this->post(route('parents.store'), [])
            ->assertSessionHasErrors(['full_name', 'gender', 'mobile_number', 'parent_status']);

        // Invalid enum values and email.
        $this->post(route('parents.store'), $this->payload([
            'gender' => 'Other',
            'parent_status' => 'Archived',
            'email' => 'not-an-email',
        ]))->assertSessionHasErrors(['gender', 'parent_status', 'email']);

        $this->assertSame(0, ParentGuardian::count());
    }

    public function test_optional_fields_may_be_omitted(): void
    {
        $this->post(route('parents.store'), [
            'full_name' => 'Minimal Parent',
            'gender' => 'Female',
            'mobile_number' => '0300-0000000',
            'parent_status' => 'Active',
        ])->assertSessionHasNoErrors();

        $parent = ParentGuardian::sole();
        $this->assertNull($parent->father_name);
        $this->assertNull($parent->cnic_number);
        $this->assertNull($parent->alternate_mobile);
        $this->assertNull($parent->email);
        $this->assertNull($parent->address);
        $this->assertNull($parent->occupation);
        $this->assertNull($parent->notes);
        $this->assertNull($parent->photo);
    }

    /* ---------------------------------------------------------------- */
    /* Photo                                                            */
    /* ---------------------------------------------------------------- */

    public function test_photo_is_optional_and_stored_as_a_path(): void
    {
        $this->post(route('parents.store'), $this->payload([
            'photo' => UploadedFile::fake()->image('rahman.jpg', 400, 400),
        ]))->assertSessionHasNoErrors();

        $parent = ParentGuardian::sole();
        $this->assertNotNull($parent->photo);
        $this->assertStringStartsWith('parents/', $parent->photo);
        Storage::disk('public')->assertExists($parent->photo);
        $this->assertTrue($parent->hasPhoto());
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
            $this->post(route('parents.store'), $this->payload(['photo' => $file]))
                ->assertSessionHasErrors('photo');
        }

        $this->assertSame(0, ParentGuardian::count());
    }

    public function test_replacing_a_photo_deletes_the_old_file(): void
    {
        $this->post(route('parents.store'), $this->payload([
            'photo' => UploadedFile::fake()->image('first.jpg'),
        ]));

        $parent = ParentGuardian::sole();
        $originalPath = $parent->photo;
        Storage::disk('public')->assertExists($originalPath);

        $this->put(route('parents.update', $parent->id), $this->payload([
            'photo' => UploadedFile::fake()->image('second.jpg'),
        ]))->assertSessionHasNoErrors();

        $parent->refresh();
        $this->assertNotSame($originalPath, $parent->photo);
        Storage::disk('public')->assertMissing($originalPath);
        Storage::disk('public')->assertExists($parent->photo);
        $this->assertCount(1, Storage::disk('public')->files('parents'));
    }

    public function test_updating_without_a_new_photo_keeps_the_existing_one(): void
    {
        $this->post(route('parents.store'), $this->payload([
            'photo' => UploadedFile::fake()->image('keep.jpg'),
        ]));

        $parent = ParentGuardian::sole();
        $originalPath = $parent->photo;

        $this->put(route('parents.update', $parent->id), $this->payload([
            'full_name' => 'Renamed Parent',
        ]))->assertSessionHasNoErrors();

        $parent->refresh();
        $this->assertSame($originalPath, $parent->photo, 'The photo must survive an edit that does not replace it');
        Storage::disk('public')->assertExists($originalPath);
    }

    public function test_deleting_a_parent_removes_the_photo_file(): void
    {
        $this->post(route('parents.store'), $this->payload([
            'photo' => UploadedFile::fake()->image('gone.jpg'),
        ]));

        $parent = ParentGuardian::sole();
        $path = $parent->photo;

        $this->delete(route('parents.destroy', $parent->id));

        Storage::disk('public')->assertMissing($path);
        $this->assertSame(0, ParentGuardian::count());
    }

    public function test_photo_is_shown_on_the_list_and_profile(): void
    {
        $this->post(route('parents.store'), $this->payload([
            'full_name' => 'Photographed Parent',
            'photo' => UploadedFile::fake()->image('shown.jpg'),
        ]));

        $parent = ParentGuardian::sole();

        $this->get(route('parents.index'))->assertOk()->assertSee($parent->photoUrl(), false);
        $this->get(route('parents.show', $parent->id))->assertOk()->assertSee($parent->photoUrl(), false);
        $this->get(route('parents.edit', $parent->id))
            ->assertOk()
            ->assertSee($parent->photoUrl(), false)
            ->assertSee('Current photo. Uploading a new one replaces it.', false);
    }

    public function test_the_placeholder_is_used_when_there_is_no_photo(): void
    {
        $parent = $this->parent(['full_name' => 'Unphotographed Parent']);

        $this->assertFalse($parent->hasPhoto());
        $this->assertNull($parent->photoUrl());
        $this->assertSame('U', $parent->initial());

        $this->get(route('parents.index'))->assertOk()->assertSee('>U<', false);
    }

    /* ---------------------------------------------------------------- */
    /* List: search, filter, pagination                                 */
    /* ---------------------------------------------------------------- */

    public function test_index_searches_by_id_name_and_mobile(): void
    {
        $this->parent(['parent_id' => 'PAR-801', 'full_name' => 'Ahmed Ali', 'mobile_number' => '0300-1111111']);
        $this->parent(['parent_id' => 'PAR-802', 'full_name' => 'Bilal Khan', 'mobile_number' => '0345-2222222']);

        $this->get(route('parents.index', ['search' => 'PAR-801']))
            ->assertOk()->assertSee('Ahmed Ali', false)->assertDontSee('Bilal Khan', false);

        $this->get(route('parents.index', ['search' => 'Bilal']))
            ->assertOk()->assertSee('Bilal Khan', false)->assertDontSee('Ahmed Ali', false);

        $this->get(route('parents.index', ['search' => '0345-2222222']))
            ->assertOk()->assertSee('Bilal Khan', false)->assertDontSee('Ahmed Ali', false);
    }

    public function test_index_filters_by_status_and_keeps_filters_across_pages(): void
    {
        $this->parent(['full_name' => 'Active Parent', 'parent_status' => 'Active']);
        $this->parent(['full_name' => 'Inactive Parent', 'parent_status' => 'Inactive']);

        $this->get(route('parents.index', ['parent_status' => 'Active']))
            ->assertOk()->assertSee('Active Parent', false)->assertDontSee('Inactive Parent', false);

        $this->get(route('parents.index', ['parent_status' => 'Inactive']))
            ->assertOk()->assertSee('Inactive Parent', false)->assertDontSee('Active Parent', false);

        // 15 matches over 10 per page: the filter survives pagination.
        foreach (range(1, 14) as $i) {
            $this->parent(['full_name' => "Paged Parent {$i}", 'parent_status' => 'Inactive']);
        }

        $response = $this->get(route('parents.index', ['parent_status' => 'Inactive']))->assertOk();
        $this->assertStringContainsString('parent_status=Inactive', $response->getContent());
        $this->assertStringContainsString('page=2', $response->getContent());

        $this->get(route('parents.index', ['parent_status' => 'Inactive', 'page' => 2]))
            ->assertOk()
            ->assertSee('Showing 11 to 15 of 15', false);
    }

    public function test_index_paginates_at_ten_per_page(): void
    {
        foreach (range(1, 12) as $i) {
            $this->parent(['full_name' => "Parent {$i}"]);
        }

        $this->get(route('parents.index'))
            ->assertOk()
            ->assertSee('Showing 1 to 10 of 12', false);

        $this->get(route('parents.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Showing 11 to 12 of 12', false);
    }

    /* ---------------------------------------------------------------- */
    /* Profile                                                          */
    /* ---------------------------------------------------------------- */

    public function test_show_page_displays_every_section(): void
    {
        $parent = $this->parent(['parent_id' => 'PAR-900']);

        $this->get(route('parents.show', $parent->id))
            ->assertOk()
            ->assertSee('Personal Information', false)
            ->assertSee('Contact Information', false)
            ->assertSee('Professional Information', false)
            ->assertSee('Additional Information', false)
            ->assertSee('PAR-900', false)
            ->assertSee('Abdul Rahman', false)
            ->assertSee('Muhammad Yousaf', false)
            ->assertSee('35201-7654321-9', false)
            ->assertSee('0300-1234567', false)
            ->assertSee('0321-7654321', false)
            ->assertSee('rahman@example.com', false)
            ->assertSee('House 12, Street 4, Lahore', false)
            ->assertSee('Shopkeeper', false)
            ->assertSee('Prefers evening calls.', false);
    }
}
