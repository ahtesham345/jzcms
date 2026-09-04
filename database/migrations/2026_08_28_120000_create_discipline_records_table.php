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
        Schema::create('discipline_records', function (Blueprint $table) {
            $table->id();

            // The student, not the enrollment. This is the one place this
            // project deliberately departs from the daily record, the
            // attendance mark and the result, all of which hang off a
            // student_academic_enrollments row.
            //
            // The reason is what a discipline incident is. A result belongs
            // to a term in a class and is meaningless outside it; an
            // incident belongs to the person. A fight in April is still
            // that student's record after they are promoted in July, and
            // tying it to the placement they happened to hold that morning
            // would make the history look like it started over.
            //
            // Nothing about the student is copied here beside this id. The
            // name, registration number, class and section are all reachable
            // through it, and a second copy could only ever drift.
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->date('date');

            // Fixed lists, stored as enums rather than free strings so a
            // report can group by them and so nothing outside the six
            // categories and three severities can be filed at all. The
            // application validates against the same lists; this is the
            // last guard behind that.
            $table->enum('category', [
                'Behavior',
                'Attendance',
                'Fighting',
                'Uniform',
                'Academic',
                'Other',
            ]);

            $table->enum('severity', ['Low', 'Medium', 'High']);

            $table->text('description');

            // Free text, deliberately. The office writes what was actually
            // done - "Verbal Warning", "Parent contacted by phone", "Sent
            // to counselling with Qari sahab" - and a fixed list would
            // either be wrong or grow forever. There is no separate action
            // or punishment table behind this column and none is wanted.
            $table->string('action_taken')->nullable();

            $table->text('remarks')->nullable();

            // Who wrote the record down. Always the authenticated user:
            // nothing accepts this from a request.
            //
            // Nulled rather than cascaded on delete. Removing a member of
            // staff must not remove a student's discipline history; the
            // incident stays and simply stops naming a recorder.
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // No unique index. A student can legitimately have two
            // incidents on the same day, in the same category, at the same
            // severity - two separate uniform checks, two separate fights -
            // and refusing the second would lose one of them.

            // One student's history, newest first, is the read this module
            // exists for: the profile summary, the history page and the
            // list all start from it.
            $table->index(['student_id', 'date'], 'dr_student_date_index');

            // The list filters by category and by severity across all
            // students, which the index above cannot serve: its leading
            // column is the student.
            $table->index(['category', 'severity'], 'dr_category_severity_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discipline_records');
    }
};
