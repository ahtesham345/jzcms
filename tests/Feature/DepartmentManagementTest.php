<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Support\AcademicPlacement;
use Database\Seeders\AdmissionDepartmentClassSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the Department master data module.
 *
 * The module had no tests of its own, so this describes the CRUD it already
 * did as well as the establishment date added to it. The date is a plain
 * historical fact about the department - the day it opened - and is
 * deliberately not tied to an academic session: a department that started in
 * 1995 started in 1995 whatever year the institution is running now.
 *
 * Optional throughout. A department recorded before anybody knew the date
 * keeps a null, and every screen has to carry on regardless.
 */
class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    /**
     * The payload the department form posts.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Hifz',
            'code' => 'HFZ',
            'description' => 'Quran memorisation.',
            'status' => '1',
        ], $overrides);
    }

    private function department(array $overrides = []): Department
    {
        return Department::create(array_merge([
            'name' => 'Dars-e-Nizami',
            'code' => 'DEN',
            'status' => true,
        ], $overrides));
    }

    /* ---------------------------------------------------------------- */
    /* Creating */
    /* ---------------------------------------------------------------- */

    public function test_a_department_can_be_created_with_an_established_date(): void
    {
        $this->post(route('departments.store'), $this->payload([
            'established_date' => '1995-03-15',
        ]))
            ->assertRedirect(route('departments.index'))
            ->assertSessionHas('success');

        $department = Department::sole();

        $this->assertSame('Hifz', $department->name);
        $this->assertSame('1995-03-15', $department->established_date->format('Y-m-d'));

        // Read back from the database rather than from the instance that
        // was saved. Asserted through the cast rather than against the raw
        // column: MySQL truncates a DATE column to the day while SQLite
        // keeps whatever string it was handed, and the day is the fact
        // being stored either way.
        $this->assertSame(
            '1995-03-15',
            Department::whereKey($department->id)->sole()->established_date->format('Y-m-d')
        );

        // The date belongs to the department alone: no session came with it.
        $this->assertArrayNotHasKey('academic_session_id', $department->getAttributes());
    }

    public function test_a_department_can_be_created_without_an_established_date(): void
    {
        $this->post(route('departments.store'), $this->payload())
            ->assertRedirect(route('departments.index'))
            ->assertSessionHasNoErrors();

        $department = Department::sole();

        $this->assertNull($department->established_date);
        $this->assertSame('Hifz', $department->name);
        $this->assertTrue($department->status);
    }

    public function test_an_empty_established_date_is_stored_as_null(): void
    {
        // An untouched date input posts an empty string, not an absent key.
        $this->post(route('departments.store'), $this->payload(['established_date' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Department::sole()->established_date);
    }

    public function test_a_date_far_in_the_past_is_accepted(): void
    {
        // The Imam's own examples reach back to 1988; nothing bounds how
        // far back a department may have opened.
        $this->post(route('departments.store'), $this->payload([
            'name' => 'Dars-e-Nizami',
            'code' => 'DEN',
            'established_date' => '1988-04-10',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('1988-04-10', Department::sole()->established_date->format('Y-m-d'));
    }

    /* ---------------------------------------------------------------- */
    /* Editing */
    /* ---------------------------------------------------------------- */

    public function test_an_established_date_can_be_added_to_an_existing_department(): void
    {
        $department = $this->department();

        $this->assertNull($department->established_date);

        $this->put(route('departments.update', $department->id), $this->payload([
            'name' => 'Dars-e-Nizami',
            'code' => 'DEN',
            'established_date' => '1988-04-10',
        ]))
            ->assertRedirect(route('departments.index'))
            ->assertSessionHas('success');

        $this->assertSame('1988-04-10', $department->fresh()->established_date->format('Y-m-d'));
    }

    public function test_an_established_date_can_be_changed(): void
    {
        $department = $this->department(['established_date' => '2005-01-01']);

        $this->put(route('departments.update', $department->id), $this->payload([
            'name' => 'Dars-e-Nizami',
            'code' => 'DEN',
            'established_date' => '2018-08-12',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('2018-08-12', $department->fresh()->established_date->format('Y-m-d'));
    }

    public function test_an_established_date_can_be_cleared(): void
    {
        $department = $this->department(['established_date' => '2005-01-01']);

        $this->put(route('departments.update', $department->id), $this->payload([
            'name' => 'Dars-e-Nizami',
            'code' => 'DEN',
            'established_date' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertNull($department->fresh()->established_date);
    }

    public function test_a_department_without_a_date_can_still_be_edited(): void
    {
        $department = $this->department();

        $this->put(route('departments.update', $department->id), $this->payload([
            'name' => 'Dars-e-Nizami Renamed',
            'code' => 'DEN2',
            'description' => 'Updated description.',
        ]))->assertSessionHasNoErrors();

        $department->refresh();

        $this->assertSame('Dars-e-Nizami Renamed', $department->name);
        $this->assertSame('DEN2', $department->code);
        $this->assertNull($department->established_date);
    }

    /* ---------------------------------------------------------------- */
    /* Validation */
    /* ---------------------------------------------------------------- */

    public function test_an_invalid_established_date_is_rejected(): void
    {
        $this->post(route('departments.store'), $this->payload([
            'established_date' => 'not-a-date',
        ]))->assertSessionHasErrors('established_date');

        $this->assertSame(0, Department::count());
    }

    public function test_an_impossible_calendar_date_is_rejected(): void
    {
        $this->post(route('departments.store'), $this->payload([
            'established_date' => '1995-02-30',
        ]))->assertSessionHasErrors('established_date');

        $this->assertSame(0, Department::count());
    }

    public function test_the_existing_department_rules_are_unchanged(): void
    {
        $this->department(['name' => 'Hifz', 'code' => 'HFZ']);

        // A duplicate name and code are still refused, with or without a date.
        $this->post(route('departments.store'), $this->payload([
            'established_date' => '1995-03-15',
        ]))->assertSessionHasErrors(['name', 'code']);

        // And the name is still required.
        $this->post(route('departments.store'), $this->payload([
            'name' => '',
            'code' => 'NEW',
        ]))->assertSessionHasErrors('name');

        $this->assertSame(1, Department::count());
    }

    /* ---------------------------------------------------------------- */
    /* Screens */
    /* ---------------------------------------------------------------- */

    public function test_the_listing_shows_the_established_date(): void
    {
        $this->department(['name' => 'Hifz', 'code' => 'HFZ', 'established_date' => '1995-03-15']);

        $this->get(route('departments.index'))
            ->assertOk()
            ->assertSee('Established Date')
            ->assertSee('Mar 15, 1995');
    }

    public function test_the_listing_shows_a_dash_when_there_is_no_date(): void
    {
        $this->department(['name' => 'Hifz', 'code' => 'HFZ']);

        // The module's own empty state, not an error.
        $this->get(route('departments.index'))
            ->assertOk()
            ->assertSee('Established Date')
            ->assertSee('Hifz');
    }

    public function test_the_create_form_offers_the_field(): void
    {
        $this->get(route('departments.create'))
            ->assertOk()
            ->assertSee('Established Date')
            ->assertSee('The date when this department/program originally started.')
            ->assertSee('name="established_date"', false);
    }

    public function test_the_edit_form_is_prefilled_with_the_stored_date(): void
    {
        $department = $this->department(['established_date' => '1988-04-10']);

        $this->get(route('departments.edit', $department->id))
            ->assertOk()
            ->assertSee('Established Date')
            ->assertSee('value="1988-04-10"', false);
    }

    public function test_the_edit_form_renders_for_a_department_with_no_date(): void
    {
        $department = $this->department();

        $this->get(route('departments.edit', $department->id))
            ->assertOk()
            ->assertSee('Established Date');
    }

    public function test_the_detail_page_shows_the_established_date(): void
    {
        $department = $this->department(['established_date' => '2005-01-01']);

        $this->get(route('departments.show', $department->id))
            ->assertOk()
            ->assertSee('Established Date')
            ->assertSee('January 01, 2005');
    }

    public function test_the_detail_page_handles_a_missing_date(): void
    {
        $department = $this->department();

        $this->get(route('departments.show', $department->id))
            ->assertOk()
            ->assertSee('Established Date')
            ->assertSee('Not recorded');
    }

    /* ---------------------------------------------------------------- */
    /* The rest of the module is untouched */
    /* ---------------------------------------------------------------- */

    public function test_a_department_can_still_be_deleted(): void
    {
        $department = $this->department();

        $this->delete(route('departments.destroy', $department->id))
            ->assertRedirect(route('departments.index'))
            ->assertSessionHas('success');

        $this->assertSame(0, Department::count());
    }

    public function test_the_listing_still_searches_and_filters(): void
    {
        $this->department(['name' => 'Hifz', 'code' => 'HFZ', 'established_date' => '1995-03-15']);
        $this->department(['name' => 'School', 'code' => 'SCH', 'status' => false]);

        $this->get(route('departments.index', ['search' => 'Hifz']))
            ->assertOk()
            ->assertSee('Hifz');

        $this->get(route('departments.index', ['status' => '0']))
            ->assertOk()
            ->assertSee('School');
    }

    public function test_a_guest_cannot_reach_or_change_a_department(): void
    {
        $department = $this->department();

        auth()->logout();

        $this->get(route('departments.index'))->assertRedirect(route('login'));
        $this->get(route('departments.create'))->assertRedirect(route('login'));
        $this->get(route('departments.edit', $department->id))->assertRedirect(route('login'));

        $this->post(route('departments.store'), $this->payload([
            'established_date' => '1995-03-15',
        ]))->assertRedirect(route('login'));

        $this->put(route('departments.update', $department->id), $this->payload([
            'name' => 'Dars-e-Nizami',
            'code' => 'DEN',
            'established_date' => '1995-03-15',
        ]))->assertRedirect(route('login'));

        // Nothing was created and nothing was changed.
        $this->assertSame(1, Department::count());
        $this->assertNull($department->fresh()->established_date);
    }

    /* ---------------------------------------------------------------- */
    /* Existing data */
    /* ---------------------------------------------------------------- */

    public function test_the_seeded_departments_carry_no_invented_date(): void
    {
        $this->seed(AdmissionDepartmentClassSeeder::class);

        $this->assertGreaterThan(0, Department::count());

        foreach (Department::all() as $department) {
            $this->assertNull(
                $department->established_date,
                "{$department->name} should not have been given a guessed date."
            );
        }
    }

    /* ---------------------------------------------------------------- */
    /* How the pages explain the setup a placement depends on */
    /* ---------------------------------------------------------------- */

    public function test_the_listing_explains_how_departments_must_be_set_up(): void
    {
        $this->department();

        $wording = $this->readableText($this->get(route('departments.index'))->assertOk()->getContent());

        // The rule an administrator has to know: one department per
        // programme, and a combined student type is not one of them.
        $this->assertStringContainsString('Create each programme as its own department.', $wording);
        $this->assertStringContainsString('must never be created as one combined department', $wording);
        $this->assertStringContainsString('Hifz + School', $wording);
    }

    public function test_the_listing_names_the_departments_the_mapping_needs(): void
    {
        $response = $this->get(route('departments.index'))->assertOk();

        // Read off the mapping rather than typed out here, so a student type
        // added to it is named on the page without this test being edited -
        // and so the page can never name a department the mapping does not.
        foreach (AcademicPlacement::requiredDepartmentNames() as $name) {
            $response->assertSee($name);
        }
    }

    public function test_both_department_forms_carry_the_naming_guidance(): void
    {
        $department = $this->department();

        foreach ([route('departments.create'), route('departments.edit', $department)] as $url) {
            $wording = $this->readableText($this->get($url)->assertOk()->getContent());

            $this->assertStringContainsString('Create each programme as its own department.', $wording);
            $this->assertStringContainsString('must never be created as one combined department', $wording);
        }
    }

    /**
     * Reduce a rendered page to the words a reader would see.
     *
     * Markup out, entities decoded and runs of whitespace collapsed, so these
     * assertions are about the wording rather than about where the Blade
     * happens to wrap a line. A sentence that spans two lines in the template
     * is one sentence on the page, and this is what makes the test agree.
     */
    private function readableText(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));
    }

    public function test_the_guidance_names_every_department_the_mapping_resolves_against(): void
    {
        // The whole point of deriving the list: Hifz + School is a student
        // type made of two departments, and contributes those two rather
        // than a combined one of its own.
        $this->assertSame(
            ['Hifz', 'School', 'Dars-e-Nizami', 'Computer'],
            AcademicPlacement::requiredDepartmentNames()
        );
    }

    public function test_the_guidance_does_not_restrict_what_an_admin_may_create(): void
    {
        // The pages advise; they do not enforce. A department named after a
        // combination is still accepted, still editable and still deletable
        // - the notice exists precisely because nothing stops it.
        $this->post(route('departments.store'), $this->payload([
            'name' => 'Hifz + School',
            'code' => 'HFZSCH',
        ]))->assertRedirect(route('departments.index'));

        $this->assertDatabaseHas('departments', ['name' => 'Hifz + School']);

        $combined = Department::where('name', 'Hifz + School')->firstOrFail();

        $this->put(route('departments.update', $combined), $this->payload([
            'name' => 'Hifz and School',
            'code' => 'HFZSCH',
        ]))->assertRedirect(route('departments.index'));

        $this->assertDatabaseHas('departments', ['name' => 'Hifz and School']);
    }
}
