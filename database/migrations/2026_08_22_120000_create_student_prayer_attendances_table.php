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
        Schema::create('student_prayer_attendances', function (Blueprint $table) {
            $table->id();

            // A separate table from student_attendances on purpose. Academic
            // attendance answers whether the student sat in class; this
            // answers whether they stood for a prayer. They are different
            // registers kept by different people, and folding them together
            // would make every later report guess which one it was reading.
            //
            // The row hangs off the enrollment rather than the student, the
            // same way academic attendance does. That is what keeps a
            // Hifz + School student's prayers on their madrassa side: the
            // school enrollment is a different row and can never be named
            // here, because only Madrassa enrollments are ever written.
            // The constraint is named explicitly. Laravel would generate
            // student_prayer_attendances_student_academic_enrollment_id_foreign,
            // which is 65 characters and one over MySQL's 64 character
            // limit for an identifier - this table's name is long enough to
            // overflow where student_attendances did not.
            $table->foreignId('student_academic_enrollment_id')
                ->constrained(
                    table: 'student_academic_enrollments',
                    indexName: 'spa_enrollment_foreign'
                )
                ->cascadeOnDelete();

            $table->date('attendance_date');

            // The five daily prayers, in the order they are prayed. The
            // order matters for display and is spelled out in the model,
            // because an enum column sorts alphabetically.
            $table->enum('prayer', ['Fajr', 'Zuhr', 'Asr', 'Maghrib', 'Isha']);

            // Present and Absent only. "Unmarked" is the absence of a row,
            // not a third value: a prayer nobody has transcribed yet must
            // not be storable as a judgement about the student.
            $table->enum('status', ['Present', 'Absent']);

            // Free text rather than an enum, matching the academic register:
            // the reasons an administrator needs are not a fixed list, and
            // locking them into the schema would make every new one a
            // migration. Optional here - a paper prayer register often
            // records only the mark.
            $table->string('absence_reason')->nullable();

            $table->timestamps();

            // The final guard against double-marking a prayer. Named
            // explicitly because the generated name would exceed MySQL's
            // 64 character limit.
            $table->unique(
                ['student_academic_enrollment_id', 'attendance_date', 'prayer'],
                'spa_enrollment_date_prayer_unique'
            );

            // Reading one day, or one prayer across a day, is the other
            // common query: the enrollments are known from the group, and
            // the date narrows them.
            $table->index(['attendance_date', 'prayer'], 'spa_date_prayer_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_prayer_attendances');
    }
};
