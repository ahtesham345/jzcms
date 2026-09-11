<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Where a Computer student currently is in their course.
     *
     * A reference to the semester record, never the words "1st Semester":
     * the stage a student is in is one of the rows the admin configured, so
     * renaming a semester or correcting its dates reaches every student
     * standing in it.
     *
     * Nullable, because it only applies to the Computer track. A Madrassa or
     * School enrollment has no semester and must not be made to carry one.
     *
     * nullOnDelete rather than cascade: removing a semester must never take
     * a student's enrollment - and with it their attendance, results and
     * academic history - down with it. The placement survives and points at
     * nothing, which is a gap an admin can see and correct.
     */
    public function up(): void
    {
        Schema::table('student_academic_enrollments', function (Blueprint $table) {
            $table->foreignId('computer_course_semester_id')
                ->nullable()
                ->after('section_id')
                ->constrained('computer_course_semesters')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_academic_enrollments', function (Blueprint $table) {
            $table->dropForeign(['computer_course_semester_id']);
            $table->dropColumn('computer_course_semester_id');
        });
    }
};
