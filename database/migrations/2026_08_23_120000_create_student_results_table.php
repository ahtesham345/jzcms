<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_results', function (Blueprint $table) {
            $table->id();

            // The result hangs off the enrollment, never off the student.
            // Same reason the daily record and the attendance mark do: the
            // session, department, class and section are read back through
            // this one id, so a First Term result recorded in Nazra /
            // Section A still reads as Nazra / Section A after the student
            // has been promoted out of it.
            //
            // It is also the only honest way to say which side of a
            // Hifz + School student a result belongs to. Such a student
            // holds a madrassa and a school enrollment at the same time,
            // and only the madrassa one may carry a result.
            //
            // No student_id, academic_session_id, department_id,
            // academic_class_id or section_id column beside it. Every one
            // of those is reachable through this foreign key, and a second
            // copy could only ever drift out of step with it.
            $table->foreignId('student_academic_enrollment_id')
                ->constrained('student_academic_enrollments')
                ->cascadeOnDelete();

            // The madrassa runs two terms. Stored as an enum rather than
            // as a free string so a report can group by it.
            $table->enum('term', ['First Term', 'Final Term']);

            // Only the Grand Test for now. An enum with one member is
            // deliberate: it is the column a later chunk widens, and until
            // then nothing else can be filed under it.
            $table->enum('test_type', ['Grand Test']);

            // Marks are decimal, not integer: half marks are normal on a
            // paper. Six digits with two decimals leaves room for a paper
            // out of 1000 without inviting a nonsense total.
            $table->decimal('total_marks', 8, 2);
            $table->decimal('obtained_marks', 8, 2);

            // Both derived, both stored. Derived because the application
            // computes them from the marks and never accepts them from a
            // request; stored because a listing sorts and filters on them
            // and recomputing per row in SQL would mean restating the
            // grading ladder in the database.
            $table->decimal('percentage', 5, 2);
            $table->string('grade', 5);

            $table->date('result_date');
            $table->text('remarks')->nullable();

            $table->timestamps();

            // One Grand Test result per enrollment per term. The enrollment
            // already stands for the student, the session and the track
            // together - student_academic_enrollments is itself unique on
            // student + session + track - so this covers the whole rule:
            // one result per enrollment + session + term + test type.
            //
            // Named explicitly because the generated name would exceed
            // MySQL's 64 character limit.
            $table->unique(
                ['student_academic_enrollment_id', 'term', 'test_type'],
                'sr_enrollment_term_test_unique'
            );

            // Drawing one term's results across a class is the other common
            // read, and the unique index above cannot serve it: its leading
            // column is the enrollment.
            $table->index(['term', 'test_type'], 'sr_term_test_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_results');
    }
};
