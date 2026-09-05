<?php

namespace Tests\Feature;

use App\Models\StudentAcademicEnrollment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\BuildsMadrassaFixtures;
use Tests\TestCase;

/**
 * Covers the index the madrassa promotion change replaced.
 *
 * The unique index on student + session + track could not stay: it applied
 * to both tracks, and the madrassa needs several placements inside one
 * session. It was replaced by a plain index on the same columns, with the
 * school half of the rule moved into Student::guardSessionBoundTrack().
 *
 * What is worth locking down is the rollback. Rolling back means restoring
 * a unique index over data that may no longer satisfy it, and DDL does not
 * roll back on MySQL or MariaDB - each statement commits on its own. So the
 * order matters: the unique index is added first and the plain one dropped
 * only once that has succeeded, which means a refused rollback leaves the
 * table exactly as it was rather than stripped of both indexes.
 *
 * These run against the test database like every other test. Nothing here
 * touches the live one.
 */
class EnrollmentIndexMigrationTest extends TestCase
{
    use BuildsMadrassaFixtures;
    use RefreshDatabase;

    private const TABLE = 'student_academic_enrollments';

    private const UNIQUE_INDEX = 'sae_student_session_track_unique';

    private const PLAIN_INDEX = 'sae_student_session_track_index';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMadrassa();
    }

    /**
     * Load a fresh instance of the migration under test.
     *
     * require rather than require_once: each call re-evaluates the file and
     * hands back the anonymous class it returns.
     */
    private function migration(): object
    {
        return require database_path(
            'migrations/2026_09_05_120000_allow_multiple_madrassa_enrollments_per_session.php'
        );
    }

    /**
     * Give one student two madrassa placements in the same session.
     */
    private function twoPlacementsInOneSession(): void
    {
        $student = $this->student('Ahmed Ali');

        $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
            'start_date' => '2026-05-01',
            'end_date' => '2026-07-15',
            'status' => 'Completed',
        ]);

        $this->madrassaEnrollment($student, [
            'start_date' => '2026-07-15',
            'status' => 'Active',
        ]);
    }

    /* ---------------------------------------------------------------- */
    /* After the migration */
    /* ---------------------------------------------------------------- */

    public function test_the_unique_index_is_gone_and_the_plain_one_is_in_place(): void
    {
        $this->assertTrue(
            Schema::hasIndex(self::TABLE, self::PLAIN_INDEX),
            'The plain index should replace the unique one.'
        );

        $this->assertFalse(
            Schema::hasIndex(self::TABLE, self::UNIQUE_INDEX),
            'The unique index should have been dropped.'
        );
    }

    public function test_the_table_accepts_two_madrassa_placements_in_one_session(): void
    {
        $this->twoPlacementsInOneSession();

        $this->assertSame(2, StudentAcademicEnrollment::count());
    }

    /* ---------------------------------------------------------------- */
    /* Rolling back */
    /* ---------------------------------------------------------------- */

    public function test_rollback_restores_the_unique_index_when_the_data_permits(): void
    {
        // One placement per session per track, so the old rule still holds.
        $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $this->migration()->down();

        $this->assertTrue(Schema::hasIndex(self::TABLE, self::UNIQUE_INDEX));
        $this->assertFalse(Schema::hasIndex(self::TABLE, self::PLAIN_INDEX));
    }

    public function test_rollback_is_refused_when_madrassa_duplicates_exist(): void
    {
        $this->twoPlacementsInOneSession();

        $this->expectException(QueryException::class);

        $this->migration()->down();
    }

    /**
     * The point of the ordering: a refused rollback changes nothing.
     */
    public function test_a_refused_rollback_leaves_the_plain_index_in_place(): void
    {
        $this->twoPlacementsInOneSession();

        try {
            $this->migration()->down();
            $this->fail('The rollback should have been refused.');
        } catch (QueryException) {
            // Expected: the unique index cannot cover this data.
        }

        // Adding the unique index first is what makes this true. Dropping
        // first would have left the table with neither index, because the
        // drop would already have committed.
        $this->assertTrue(
            Schema::hasIndex(self::TABLE, self::PLAIN_INDEX),
            'A refused rollback must not remove the plain index.'
        );

        $this->assertFalse(Schema::hasIndex(self::TABLE, self::UNIQUE_INDEX));
    }

    public function test_a_refused_rollback_leaves_the_enrollments_untouched(): void
    {
        $this->twoPlacementsInOneSession();

        try {
            $this->migration()->down();
        } catch (QueryException) {
            // Expected.
        }

        $this->assertSame(2, StudentAcademicEnrollment::count());
    }
}
