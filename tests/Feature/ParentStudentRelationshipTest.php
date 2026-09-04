<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\ParentGuardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ParentStudentRelationshipTest extends TestCase
{
    use RefreshDatabase;

    private AcademicSession $session;

    private Department $department;

    private AcademicClass $class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->seed(\Database\Seeders\AdmissionDepartmentClassSeeder::class);

        $this->session = AcademicSession::create([
            'name' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
            'status' => true,
        ]);

        $this->department = Department::where('name', 'Hifz')->firstOrFail();
        $this->class = AcademicClass::where('department_id', $this->department->id)->firstOrFail();
    }

    private function parent(array $overrides = []): ParentGuardian
    {
        return ParentGuardian::createWithParentId(array_merge([
            'full_name' => 'Muhammad Ali',
            'gender' => 'Male',
            'mobile_number' => '0300-1234567',
            'parent_status' => 'Active',
        ], $overrides));
    }

    private function student(array $overrides = []): Student
    {
        static $sequence = 0;
        $sequence++;

        return Student::create(array_merge([
            'registration_number' => 'STD-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'full_name' => 'Ahmed Ali',
            'father_name' => 'Muhammad Ali',
            'gender' => 'Male',
            'father_mobile' => '0300-1234567',
            'emergency_contact' => '0300-7654321',
            'admission_date' => '2026-04-01',
            'academic_session_id' => $this->session->id,
            'department_id' => $this->department->id,
            'academic_class_id' => $this->class->id,
            'student_status' => 'Active',
            'student_type' => 'Hifz',
            'resident_type' => 'Local Resident',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function linkPayload(Student $student, array $overrides = []): array
    {
        return array_merge([
            'student_id' => $student->id,
            'relationship_type' => 'Father',
            'is_primary' => '0',
        ], $overrides);
    }

    /* ---------------------------------------------------------------- */
    /* Relationships                                                    */
    /* ---------------------------------------------------------------- */

    public function test_one_parent_can_have_multiple_students(): void
    {
        $parent = $this->parent();
        $first = $this->student(['full_name' => 'Ahmed Ali']);
        $second = $this->student(['full_name' => 'Bilal Ali']);

        $parent->linkStudent($first->id, 'Father', true);
        $parent->linkStudent($second->id, 'Father', true);

        $this->assertCount(2, $parent->fresh()->students);
        $this->assertEqualsCanonicalizing(
            ['Ahmed Ali', 'Bilal Ali'],
            $parent->fresh()->students->pluck('full_name')->all()
        );
    }

    public function test_one_student_can_have_multiple_parents(): void
    {
        $student = $this->student();
        $father = $this->parent(['full_name' => 'Muhammad Ali']);
        $mother = $this->parent(['full_name' => 'Fatima Bibi', 'gender' => 'Female']);
        $guardian = $this->parent(['full_name' => 'Uncle Hassan']);

        $father->linkStudent($student->id, 'Father', true);
        $mother->linkStudent($student->id, 'Mother', true);
        $guardian->linkStudent($student->id, 'Guardian', false);

        $this->assertCount(3, $student->fresh()->parents);
        $this->assertEqualsCanonicalizing(
            ['Muhammad Ali', 'Fatima Bibi', 'Uncle Hassan'],
            $student->fresh()->parents->pluck('full_name')->all()
        );
    }

    public function test_each_relationship_type_works(): void
    {
        $student = $this->student();

        foreach (ParentGuardian::RELATIONSHIP_TYPES as $type) {
            $parent = $this->parent(['full_name' => "The {$type}"]);
            $parent->linkStudent($student->id, $type, true);
        }

        $links = $student->fresh()->parents;
        $this->assertCount(3, $links);
        $this->assertEqualsCanonicalizing(
            ['Father', 'Mother', 'Guardian'],
            $links->pluck('pivot.relationship_type')->all()
        );
    }

    public function test_the_pivot_stores_the_relationship_type_and_primary_flag(): void
    {
        $parent = $this->parent();
        $student = $this->student();

        $parent->linkStudent($student->id, 'Guardian', true);

        $pivot = $student->fresh()->parents->sole()->pivot;
        $this->assertSame('Guardian', $pivot->relationship_type);
        $this->assertTrue($pivot->is_primary, 'is_primary must be cast to a real boolean');

        // And a non primary link stores false.
        $other = $this->parent(['full_name' => 'Second Guardian']);
        $other->linkStudent($student->id, 'Guardian', false);

        $this->assertFalse(
            $student->fresh()->parents->firstWhere('full_name', 'Second Guardian')->pivot->is_primary
        );
    }

    public function test_the_relationship_types_come_from_the_parent_model(): void
    {
        $this->assertSame(['Father', 'Mother', 'Guardian'], ParentGuardian::RELATIONSHIP_TYPES);
    }

    /* ---------------------------------------------------------------- */
    /* Duplicate and primary rules                                      */
    /* ---------------------------------------------------------------- */

    public function test_a_duplicate_parent_student_link_is_rejected(): void
    {
        $parent = $this->parent();
        $student = $this->student();

        $this->post(route('parents.students.store', $parent->id), $this->linkPayload($student))
            ->assertSessionHasNoErrors();

        // Same pair again, even with a different relationship.
        $this->post(route('parents.students.store', $parent->id), $this->linkPayload($student, [
            'relationship_type' => 'Guardian',
        ]))->assertSessionHasErrors('student_id');

        $this->assertSame(1, DB::table('parent_student')->count());
    }

    public function test_the_unique_index_is_the_final_guard_against_duplicates(): void
    {
        $parent = $this->parent();
        $student = $this->student();
        $parent->linkStudent($student->id, 'Father', false);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        // Straight past the model helper and its checks.
        DB::table('parent_student')->insert([
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'relationship_type' => 'Mother',
            'is_primary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_two_primary_fathers_for_the_same_student_are_rejected(): void
    {
        $student = $this->student();
        $first = $this->parent(['full_name' => 'First Father']);
        $second = $this->parent(['full_name' => 'Second Father']);

        $this->post(route('parents.students.store', $first->id), $this->linkPayload($student, [
            'relationship_type' => 'Father',
            'is_primary' => '1',
        ]))->assertSessionHasNoErrors();

        $this->post(route('parents.students.store', $second->id), $this->linkPayload($student, [
            'relationship_type' => 'Father',
            'is_primary' => '1',
        ]))->assertSessionHasErrors('is_primary');

        $this->assertSame(1, DB::table('parent_student')->count());
    }

    public function test_two_primary_mothers_for_the_same_student_are_rejected(): void
    {
        $student = $this->student();
        $first = $this->parent(['full_name' => 'First Mother', 'gender' => 'Female']);
        $second = $this->parent(['full_name' => 'Second Mother', 'gender' => 'Female']);

        $this->post(route('parents.students.store', $first->id), $this->linkPayload($student, [
            'relationship_type' => 'Mother',
            'is_primary' => '1',
        ]))->assertSessionHasNoErrors();

        $this->post(route('parents.students.store', $second->id), $this->linkPayload($student, [
            'relationship_type' => 'Mother',
            'is_primary' => '1',
        ]))->assertSessionHasErrors('is_primary');

        $this->assertSame(1, DB::table('parent_student')->count());
    }

    public function test_the_primary_rule_is_enforced_below_the_request_layer(): void
    {
        $student = $this->student();
        $this->parent(['full_name' => 'First Father'])->linkStudent($student->id, 'Father', true);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->parent(['full_name' => 'Second Father'])->linkStudent($student->id, 'Father', true);
    }

    public function test_a_second_non_primary_of_the_same_type_is_allowed(): void
    {
        $student = $this->student();
        $primary = $this->parent(['full_name' => 'Primary Guardian']);
        $secondary = $this->parent(['full_name' => 'Secondary Guardian']);

        $primary->linkStudent($student->id, 'Guardian', true);
        $secondary->linkStudent($student->id, 'Guardian', false);

        $this->assertCount(2, $student->fresh()->parents);
    }

    public function test_the_same_parent_may_be_primary_for_different_students(): void
    {
        $parent = $this->parent();
        $first = $this->student(['full_name' => 'First Child']);
        $second = $this->student(['full_name' => 'Second Child']);

        $parent->linkStudent($first->id, 'Father', true);
        $parent->linkStudent($second->id, 'Father', true);

        $this->assertSame(2, DB::table('parent_student')->where('is_primary', true)->count());
    }

    /* ---------------------------------------------------------------- */
    /* Unlinking                                                        */
    /* ---------------------------------------------------------------- */

    public function test_unlinking_removes_only_the_pivot_record(): void
    {
        $parent = $this->parent();
        $student = $this->student();
        $parent->linkStudent($student->id, 'Father', true);

        $this->delete(route('parents.students.destroy', [$parent->id, $student->id]))
            ->assertRedirect(route('parents.show', $parent->id))
            ->assertSessionHas('success');

        $this->assertSame(0, DB::table('parent_student')->count());

        // Both records survive untouched.
        $this->assertNotNull($parent->fresh());
        $this->assertNotNull($student->fresh());
        $this->assertSame(1, ParentGuardian::count());
        $this->assertSame(1, Student::count());
        $this->assertSame('Ahmed Ali', $student->fresh()->full_name);
        $this->assertSame('Muhammad Ali', $student->fresh()->father_name);
    }

    public function test_unlinking_from_the_student_page_removes_only_the_pivot(): void
    {
        $parent = $this->parent();
        $student = $this->student();
        $parent->linkStudent($student->id, 'Mother', false);

        $this->delete(route('students.parents.destroy', [$student->id, $parent->id]))
            ->assertRedirect(route('students.show', $student->id))
            ->assertSessionHas('success');

        $this->assertSame(0, DB::table('parent_student')->count());
        $this->assertSame(1, ParentGuardian::count());
        $this->assertSame(1, Student::count());
    }

    public function test_unlinking_from_the_student_side_clears_the_link_on_both_profiles(): void
    {
        $parent = $this->parent(['full_name' => 'Muhammad Ali']);
        $student = $this->student(['full_name' => 'Ahmed Ali', 'registration_number' => 'STD-9001']);
        $parent->linkStudent($student->id, 'Father', true);

        // Both profiles show the link to begin with.
        $this->get(route('students.show', $student->id))->assertOk()->assertSee('Muhammad Ali', false);
        $this->get(route('parents.show', $parent->id))->assertOk()->assertSee('STD-9001', false);

        $this->delete(route('students.parents.destroy', [$student->id, $parent->id]))
            ->assertRedirect(route('students.show', $student->id));

        // The pivot row itself.
        $this->assertDatabaseMissing('parent_student', [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
        ]);
        $this->assertSame(0, DB::table('parent_student')->count());

        // Both relations, read fresh from the database.
        $this->assertFalse($student->fresh()->parents->contains('id', $parent->id));
        $this->assertFalse($parent->fresh()->students->contains('id', $student->id));

        // And both rendered profiles. The assertion is on the row's link to
        // the other profile, which only the linked-records table renders:
        // the name alone also appears in the "Link Student" select, where an
        // unlinked student is correctly offered again.
        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('No parents or guardians linked.', false)
            ->assertDontSee('href="'.route('parents.show', $parent->id).'"', false);

        $this->get(route('parents.show', $parent->id))
            ->assertOk()
            ->assertSee('No students linked.', false)
            ->assertDontSee('href="'.route('students.show', $student->id).'"', false);

        // Neither record was deleted.
        $this->assertSame(1, ParentGuardian::count());
        $this->assertSame(1, Student::count());
    }

    public function test_unlinking_from_the_parent_side_clears_the_link_on_both_profiles(): void
    {
        $parent = $this->parent(['full_name' => 'Muhammad Ali']);
        $student = $this->student(['full_name' => 'Ahmed Ali', 'registration_number' => 'STD-9002']);
        $parent->linkStudent($student->id, 'Father', true);

        $this->delete(route('parents.students.destroy', [$parent->id, $student->id]))
            ->assertRedirect(route('parents.show', $parent->id));

        $this->assertDatabaseMissing('parent_student', [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
        ]);
        $this->assertSame(0, DB::table('parent_student')->count());

        $this->assertFalse($parent->fresh()->students->contains('id', $student->id));
        $this->assertFalse($student->fresh()->parents->contains('id', $parent->id));

        $this->get(route('parents.show', $parent->id))
            ->assertOk()
            ->assertSee('No students linked.', false)
            ->assertDontSee('href="'.route('students.show', $student->id).'"', false);

        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('No parents or guardians linked.', false)
            ->assertDontSee('href="'.route('parents.show', $parent->id).'"', false);

        $this->assertSame(1, ParentGuardian::count());
        $this->assertSame(1, Student::count());
    }

    public function test_unlinking_the_right_row_when_the_parent_has_several_children(): void
    {
        // The scenario a wrong-way-round route parameter would corrupt: the
        // unlinked child must go, and only that one.
        $parent = $this->parent();
        $keep = $this->student(['full_name' => 'Keep Me', 'registration_number' => 'STD-9101']);
        $remove = $this->student(['full_name' => 'Remove Me', 'registration_number' => 'STD-9102']);

        $parent->linkStudent($keep->id, 'Father', true);
        $parent->linkStudent($remove->id, 'Father', true);

        $this->delete(route('students.parents.destroy', [$remove->id, $parent->id]));

        $this->assertDatabaseHas('parent_student', ['parent_id' => $parent->id, 'student_id' => $keep->id]);
        $this->assertDatabaseMissing('parent_student', ['parent_id' => $parent->id, 'student_id' => $remove->id]);

        $this->get(route('parents.show', $parent->id))
            ->assertOk()
            ->assertSee('href="'.route('students.show', $keep->id).'"', false)
            ->assertDontSee('href="'.route('students.show', $remove->id).'"', false);
    }

    public function test_unlinking_one_link_leaves_the_others(): void
    {
        $student = $this->student();
        $father = $this->parent(['full_name' => 'The Father']);
        $mother = $this->parent(['full_name' => 'The Mother', 'gender' => 'Female']);

        $father->linkStudent($student->id, 'Father', true);
        $mother->linkStudent($student->id, 'Mother', true);

        $this->delete(route('students.parents.destroy', [$student->id, $father->id]));

        $remaining = $student->fresh()->parents;
        $this->assertCount(1, $remaining);
        $this->assertSame('The Mother', $remaining->sole()->full_name);
    }

    public function test_unlinking_frees_the_primary_slot(): void
    {
        $student = $this->student();
        $first = $this->parent(['full_name' => 'First Father']);
        $second = $this->parent(['full_name' => 'Second Father']);

        $first->linkStudent($student->id, 'Father', true);
        $this->delete(route('parents.students.destroy', [$first->id, $student->id]));

        // With the slot free the next primary Father is accepted.
        $this->post(route('parents.students.store', $second->id), $this->linkPayload($student, [
            'relationship_type' => 'Father',
            'is_primary' => '1',
        ]))->assertSessionHasNoErrors();

        $this->assertTrue($student->fresh()->parents->sole()->pivot->is_primary);
    }

    public function test_deleting_a_parent_removes_the_links_but_not_the_students(): void
    {
        $parent = $this->parent();
        $student = $this->student();
        $parent->linkStudent($student->id, 'Father', true);

        $this->delete(route('parents.destroy', $parent->id))->assertSessionHas('success');

        $this->assertSame(0, DB::table('parent_student')->count());
        $this->assertSame(1, Student::count());
        $this->assertNotNull($student->fresh());
    }

    public function test_deleting_a_student_removes_the_links_but_not_the_parents(): void
    {
        $parent = $this->parent();
        $student = $this->student();
        $parent->linkStudent($student->id, 'Father', true);

        $this->delete(route('students.destroy', $student->id))->assertSessionHas('success');

        $this->assertSame(0, DB::table('parent_student')->count());
        $this->assertSame(1, ParentGuardian::count());
        $this->assertNotNull($parent->fresh());
    }

    /* ---------------------------------------------------------------- */
    /* Validation                                                       */
    /* ---------------------------------------------------------------- */

    public function test_linking_requires_a_valid_student(): void
    {
        $parent = $this->parent();

        $this->post(route('parents.students.store', $parent->id), [
            'student_id' => 999999,
            'relationship_type' => 'Father',
        ])->assertSessionHasErrors('student_id');

        $this->post(route('parents.students.store', $parent->id), [
            'student_id' => 'not-an-id',
            'relationship_type' => 'Father',
        ])->assertSessionHasErrors('student_id');

        $this->post(route('parents.students.store', $parent->id), [
            'relationship_type' => 'Father',
        ])->assertSessionHasErrors('student_id');

        $this->assertSame(0, DB::table('parent_student')->count());
    }

    public function test_linking_requires_a_valid_parent(): void
    {
        $student = $this->student();

        $this->post(route('students.parents.store', $student->id), [
            'parent_id' => 999999,
            'relationship_type' => 'Father',
        ])->assertSessionHasErrors('parent_id');

        $this->post(route('students.parents.store', $student->id), [
            'relationship_type' => 'Father',
        ])->assertSessionHasErrors('parent_id');

        $this->assertSame(0, DB::table('parent_student')->count());
    }

    public function test_the_relationship_type_is_required_and_must_be_valid(): void
    {
        $parent = $this->parent();
        $student = $this->student();

        $this->post(route('parents.students.store', $parent->id), [
            'student_id' => $student->id,
        ])->assertSessionHasErrors('relationship_type');

        $this->post(route('parents.students.store', $parent->id), [
            'student_id' => $student->id,
            'relationship_type' => 'Uncle',
        ])->assertSessionHasErrors('relationship_type');

        $this->assertSame(0, DB::table('parent_student')->count());
    }

    public function test_a_forged_parent_id_in_the_body_cannot_override_the_route(): void
    {
        $routeParent = $this->parent(['full_name' => 'Route Parent']);
        $otherParent = $this->parent(['full_name' => 'Other Parent']);
        $student = $this->student();

        // The parent comes from the URL; a parent_id in the body is ignored.
        $this->post(route('parents.students.store', $routeParent->id), $this->linkPayload($student, [
            'parent_id' => $otherParent->id,
        ]))->assertSessionHasNoErrors();

        $this->assertCount(1, $routeParent->fresh()->students);
        $this->assertCount(0, $otherParent->fresh()->students);
    }

    public function test_link_and_unlink_require_authentication(): void
    {
        $parent = $this->parent();
        $student = $this->student();
        auth()->logout();

        $this->post(route('parents.students.store', $parent->id), $this->linkPayload($student))
            ->assertRedirect(route('login'));
        $this->post(route('students.parents.store', $student->id), ['parent_id' => $parent->id, 'relationship_type' => 'Father'])
            ->assertRedirect(route('login'));
        $this->delete(route('parents.students.destroy', [$parent->id, $student->id]))
            ->assertRedirect(route('login'));

        $this->assertSame(0, DB::table('parent_student')->count());
    }

    public function test_unlinking_an_unknown_record_is_a_404(): void
    {
        $parent = $this->parent();
        $student = $this->student();

        $this->delete(route('parents.students.destroy', [$parent->id, 999999]))->assertNotFound();
        $this->delete(route('parents.students.destroy', [999999, $student->id]))->assertNotFound();
    }

    /* ---------------------------------------------------------------- */
    /* UI                                                               */
    /* ---------------------------------------------------------------- */

    public function test_linking_a_student_from_the_parent_page_works(): void
    {
        $parent = $this->parent();
        $student = $this->student();

        $this->post(route('parents.students.store', $parent->id), $this->linkPayload($student, [
            'relationship_type' => 'Father',
            'is_primary' => '1',
        ]))
            ->assertRedirect(route('parents.show', $parent->id))
            ->assertSessionHas('success');

        $pivot = $parent->fresh()->students->sole()->pivot;
        $this->assertSame('Father', $pivot->relationship_type);
        $this->assertTrue($pivot->is_primary);
    }

    public function test_linking_a_parent_from_the_student_page_works(): void
    {
        $parent = $this->parent();
        $student = $this->student();

        $this->post(route('students.parents.store', $student->id), [
            'parent_id' => $parent->id,
            'relationship_type' => 'Mother',
            'is_primary' => '1',
        ])
            ->assertRedirect(route('students.show', $student->id))
            ->assertSessionHas('success');

        $pivot = $student->fresh()->parents->sole()->pivot;
        $this->assertSame('Mother', $pivot->relationship_type);
        $this->assertTrue($pivot->is_primary);
    }

    public function test_the_parent_page_lists_linked_students(): void
    {
        $parent = $this->parent();
        $student = $this->student(['full_name' => 'Ahmed Ali', 'registration_number' => 'STD-7001']);
        $parent->linkStudent($student->id, 'Father', true);

        $this->get(route('parents.show', $parent->id))
            ->assertOk()
            ->assertSee('Children / Students', false)
            ->assertSee('Ahmed Ali', false)
            ->assertSee('STD-7001', false)
            ->assertSee('Hifz', false)
            ->assertSee('Father', false)
            ->assertSee('Primary', false)
            ->assertSee(route('students.show', $student->id), false)
            ->assertSee('Unlink', false)
            ->assertDontSee('No students linked.', false);
    }

    public function test_the_student_page_lists_linked_parents(): void
    {
        $parent = $this->parent(['full_name' => 'Muhammad Ali', 'mobile_number' => '0300-9998887']);
        $student = $this->student();
        $parent->linkStudent($student->id, 'Guardian', true);

        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('Parents / Guardians', false)
            ->assertSee('Muhammad Ali', false)
            ->assertSee($parent->parent_id, false)
            ->assertSee('0300-9998887', false)
            ->assertSee('Guardian', false)
            ->assertSee('Primary', false)
            ->assertSee(route('parents.show', $parent->id), false)
            ->assertSee('Unlink', false)
            ->assertDontSee('No parents or guardians linked.', false);
    }

    public function test_the_empty_states_render(): void
    {
        $parent = $this->parent();
        $student = $this->student();

        $this->get(route('parents.show', $parent->id))
            ->assertOk()
            ->assertSee('No students linked.', false)
            ->assertSee('Link Student', false);

        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('No parents or guardians linked.', false)
            ->assertSee('Link Parent', false);
    }

    public function test_the_link_forms_offer_only_unlinked_records(): void
    {
        $parent = $this->parent();
        $linked = $this->student(['full_name' => 'Linked Child', 'registration_number' => 'STD-7100']);
        $this->student(['full_name' => 'Unlinked Child', 'registration_number' => 'STD-7200']);
        $parent->linkStudent($linked->id, 'Father', true);

        // The already linked student is not offered again in the select.
        $this->get(route('parents.show', $parent->id))
            ->assertOk()
            ->assertSee('STD-7200 — Unlinked Child', false)
            ->assertDontSee('STD-7100 — Linked Child', false);

        // The same from the student side.
        $other = $this->parent(['full_name' => 'Unlinked Parent']);
        $this->get(route('students.show', $linked->id))
            ->assertOk()
            ->assertSee($other->parent_id.' — Unlinked Parent', false)
            ->assertDontSee($parent->parent_id.' — Muhammad Ali', false);
    }

    public function test_the_existing_student_parent_fields_are_untouched(): void
    {
        $parent = $this->parent(['full_name' => 'Different Person']);
        $student = $this->student([
            'father_name' => 'Original Father',
            'father_mobile' => '0300-1111111',
            'mother_mobile' => '0300-2222222',
            'emergency_contact' => '0300-3333333',
        ]);

        $parent->linkStudent($student->id, 'Father', true);
        $this->delete(route('parents.students.destroy', [$parent->id, $student->id]));

        $student->refresh();
        $this->assertSame('Original Father', $student->father_name);
        $this->assertSame('0300-1111111', $student->father_mobile);
        $this->assertSame('0300-2222222', $student->mother_mobile);
        $this->assertSame('0300-3333333', $student->emergency_contact);
    }
}
