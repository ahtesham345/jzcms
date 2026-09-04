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
        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();

            // Attendance hangs off the enrollment, never off the student:
            // a Hifz + School student holds two active enrollments and each
            // one is marked separately. Deleting an enrollment takes its
            // attendance with it, the same way the academic history does.
            $table->foreignId('student_academic_enrollment_id')
                ->constrained('student_academic_enrollments')
                ->cascadeOnDelete();

            $table->date('attendance_date');

            // School is restricted to Morning by the application; the column
            // carries all three because one table serves both tracks.
            $table->enum('attendance_period', ['Morning', 'Afternoon', 'Evening']);

            $table->enum('status', ['Present', 'Absent']);

            // Free text rather than an enum: the reasons an administrator
            // needs are not a fixed list, and locking them into the schema
            // now would make every new one a migration.
            $table->string('absence_reason')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            // The final guard against double-marking a sheet. Named
            // explicitly because the generated name would exceed MySQL's
            // 64 character limit.
            $table->unique(
                ['student_academic_enrollment_id', 'attendance_date', 'attendance_period'],
                'sa_enrollment_date_period_unique'
            );

            // Loading one day's sheet is the common read: the enrollments
            // are known from the group, the date and period narrow them.
            $table->index(
                ['attendance_date', 'attendance_period'],
                'sa_date_period_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_attendances');
    }
};
