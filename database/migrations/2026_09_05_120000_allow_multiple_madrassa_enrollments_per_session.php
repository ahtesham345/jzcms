<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The institution runs one academic session across the school and the
     * madrassa, but the two do not progress the same way. A school class
     * runs for the academic year, so one enrollment per session per track
     * was right. A madrassa stage finishes when the student finishes it -
     * Nazra may be completed in July of a session that runs to the
     * following April - and the Imam promotes them then.
     *
     * The unique index added with the session column enforced that rule for
     * both tracks, which made a mid-session madrassa promotion impossible:
     * the only session the student could be promoted into was the next one,
     * so in practice a promotion had to wait for the session to end.
     *
     * The rule is not being dropped, it is being scoped to the school. It
     * cannot stay a unique index, because a unique index cannot be
     * conditional on a column value in a way that is portable to MySQL - the
     * same reason ParentGuardian::linkStudent() re-checks "one primary per
     * relationship type" in PHP. So the index becomes a plain one, which
     * keeps the lookup it was also serving, and Student::promote() and the
     * enrollment form requests enforce the school rule, the promote() check
     * running inside the promotion transaction under a row lock.
     */
    public function up(): void
    {
        Schema::table('student_academic_enrollments', function (Blueprint $table) {
            $table->dropUnique('sae_student_session_track_unique');

            // Same columns, still indexed: reading a student's enrollments
            // for one session and track stays a single index lookup.
            $table->index(
                ['student_id', 'academic_session_id', 'academic_track'],
                'sae_student_session_track_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * Restoring the unique index fails if any madrassa student has since
     * been promoted more than once inside one session. That is the data this
     * migration exists to allow, so those rows must be resolved by hand
     * before rolling back.
     *
     * The unique index is added first and the plain one dropped only after
     * that has succeeded. The order matters because DDL is not
     * transactional in MySQL and MariaDB: each statement commits on its own,
     * so dropping first would leave the table with neither index whenever
     * the duplicate data above makes the unique index impossible. Adding
     * first means a refused rollback changes nothing at all.
     */
    public function down(): void
    {
        Schema::table('student_academic_enrollments', function (Blueprint $table) {
            $table->unique(
                ['student_id', 'academic_session_id', 'academic_track'],
                'sae_student_session_track_unique'
            );
        });

        Schema::table('student_academic_enrollments', function (Blueprint $table) {
            $table->dropIndex('sae_student_session_track_index');
        });
    }
};
