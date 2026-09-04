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
        Schema::create('madrassa_daily_records', function (Blueprint $table) {
            $table->id();

            // The record hangs off the enrollment, never off the student.
            // That is what makes it historically accurate: the session,
            // department, class and section are read back through this one
            // id, so a record made in Nazra / Section A still reads as
            // Nazra / Section A after the student is promoted to Hifz.
            //
            // It is also what keeps a Hifz + School student honest. Such a
            // student holds one madrassa and one school enrollment at the
            // same time, and naming the madrassa one here is the only way
            // to say which of the two the day's work belongs to.
            $table->foreignId('student_academic_enrollment_id')
                ->constrained('student_academic_enrollments')
                ->cascadeOnDelete();

            $table->date('record_date');

            // Stored rather than derived from the student's current
            // student_type. The type decides which set of columns below is
            // filled in, so a Hifz record must keep reading as a Hifz
            // record even if the student later moves to Dars-e-Nizami.
            $table->enum('record_type', ['Hifz', 'Dars-e-Nizami']);

            // Optional: the teacher is chosen from the existing Teacher
            // module when the office knows who took the lesson. Nulled
            // rather than cascaded on delete, because removing a teacher
            // must not remove a student's academic history.
            $table->foreignId('teacher_id')
                ->nullable()
                ->constrained('teachers')
                ->nullOnDelete();

            // ---- Hifz --------------------------------------------------
            //
            // The quantities are strings, deliberately. A day's work is
            // recorded as the madrassa says it: "1 page", "half page",
            // "1/2 para", "1 para". Forcing that into a number would mean
            // inventing a page/para arithmetic this project does not have
            // and losing what the teacher actually wrote.
            $table->string('sabaq')->nullable();
            $table->string('sabaq_quantity')->nullable();
            $table->string('sabqi')->nullable();
            $table->string('sabqi_quantity')->nullable();
            $table->string('manzil')->nullable();
            $table->string('manzil_quantity')->nullable();
            $table->string('next_sabaq')->nullable();

            // ---- Dars-e-Nizami -----------------------------------------
            //
            // A separate set of columns rather than reusing the Hifz ones
            // under vaguer names: a kitab lesson and a day's memorisation
            // are not the same thing, and a report that had to guess which
            // one a column meant would be worse than a few nullable
            // columns.
            $table->string('subject_book')->nullable();
            $table->string('todays_lesson')->nullable();
            $table->string('lesson_topic_covered')->nullable();
            $table->string('revision')->nullable();
            $table->string('next_lesson')->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();

            // One record per student per day. The enrollment stands for the
            // student and the track together, so this is the final guard
            // against a second record being opened instead of the first one
            // being corrected. Named explicitly because the generated name
            // would exceed MySQL's 64 character limit.
            $table->unique(
                ['student_academic_enrollment_id', 'record_date'],
                'mdr_enrollment_date_unique'
            );

            // Reading a single day across a class is the other common
            // query, and the unique index above cannot serve it: its
            // leading column is the enrollment.
            $table->index('record_date', 'mdr_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('madrassa_daily_records');
    }
};
