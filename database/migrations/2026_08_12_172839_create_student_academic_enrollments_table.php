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
        Schema::create('student_academic_enrollments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->enum('academic_track', ['Madrassa', 'School']);

            $table->foreignId('department_id')
                ->constrained('departments')
                ->cascadeOnDelete();

            $table->foreignId('academic_class_id')
                ->constrained('academic_classes')
                ->cascadeOnDelete();

            // Optional: a class may be run without sections.
            $table->foreignId('section_id')
                ->nullable()
                ->constrained('sections')
                ->nullOnDelete();

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->enum('status', ['Active', 'Completed', 'Left'])->default('Active');

            $table->timestamps();

            // Looking up a student's current enrollment per track is the
            // common read, so index it. Named explicitly because the
            // generated name would exceed MySQL's 64 character limit.
            $table->index(
                ['student_id', 'academic_track', 'status'],
                'sae_student_track_status_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_academic_enrollments');
    }
};
