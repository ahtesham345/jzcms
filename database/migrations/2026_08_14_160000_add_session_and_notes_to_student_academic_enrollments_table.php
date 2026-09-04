<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_academic_enrollments', function (Blueprint $table) {
            // Nullable at the database level only because the column is
            // being added to a table that already holds rows. Every write
            // path sets it and both request classes require it.
            $table->foreignId('academic_session_id')
                ->nullable()
                ->after('student_id')
                ->constrained('academic_sessions')
                ->cascadeOnDelete();

            $table->text('notes')->nullable()->after('status');
        });

        $this->backfillSessions();

        Schema::table('student_academic_enrollments', function (Blueprint $table) {
            // One enrollment per student per session per track. Scoped by
            // track deliberately: a Hifz + School student holds one madrassa
            // and one school enrollment in the same session, and both are
            // legitimate. Named explicitly because the generated name would
            // exceed MySQL's 64 character limit.
            $table->unique(
                ['student_id', 'academic_session_id', 'academic_track'],
                'sae_student_session_track_unique'
            );

            // Reading a session's enrollments is the other common query.
            $table->index('academic_session_id', 'sae_session_index');
        });
    }

    /**
     * Give the existing enrollments the session their student belongs to.
     *
     * Not a guess: the approval flow created these rows using the same
     * academic_session_id it wrote onto the student, so this restores the
     * value the row would have carried had the column existed then.
     */
    private function backfillSessions(): void
    {
        $sessions = DB::table('students')->pluck('academic_session_id', 'id');

        $rows = DB::table('student_academic_enrollments')
            ->whereNull('academic_session_id')
            ->get(['id', 'student_id']);

        foreach ($rows as $row) {
            if (! isset($sessions[$row->student_id])) {
                continue;
            }

            DB::table('student_academic_enrollments')
                ->where('id', $row->id)
                ->update(['academic_session_id' => $sessions[$row->student_id]]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_academic_enrollments', function (Blueprint $table) {
            $table->dropUnique('sae_student_session_track_unique');
            $table->dropIndex('sae_session_index');
            $table->dropConstrainedForeignId('academic_session_id');
            $table->dropColumn('notes');
        });
    }
};
