<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The academic session an application is applying for. Nullable, and
     * deliberately left null on every row that already exists: nothing on an
     * application records the session it was submitted for, and no date on it
     * reliably implies one - the intake for an April session is taken during
     * the previous one. A guessed session would put a candidate on the wrong
     * notice board, so the rows stay honest about not knowing.
     *
     * nullOnDelete rather than cascade, matching the class columns beside it:
     * deleting a session must never delete admission applications.
     */
    public function up(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->foreignId('academic_session_id')
                ->nullable()
                ->after('student_type')
                ->constrained('academic_sessions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->dropForeign(['academic_session_id']);
            $table->dropColumn('academic_session_id');
        });
    }
};
