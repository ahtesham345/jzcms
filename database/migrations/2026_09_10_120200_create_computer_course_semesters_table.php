<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per stage of a Computer course. The order is what makes the
     * course a progression, so it is unique within the course: there is one
     * first semester, one second, and no way to end up with two.
     *
     * The dates are nullable on purpose. The six semesters exist as soon as
     * the course does, but only the admin knows when each one actually runs,
     * and seeding a guess would put invented dates on the curriculum. A
     * semester with no dates yet is an undated semester, not a wrong one.
     *
     * The curriculum is one text column rather than a subject table. What
     * the business asked for is "what will be taught" as the admin writes
     * it, several lines of it; a subject module would be a second academic
     * structure nothing has asked to query.
     */
    public function up(): void
    {
        Schema::create('computer_course_semesters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('computer_course_id')
                ->constrained('computer_courses')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('order');
            $table->string('name');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->text('curriculum')->nullable();

            $table->boolean('status')->default(true);

            $table->timestamps();

            // One stage per position in the course.
            $table->unique(['computer_course_id', 'order'], 'ccs_course_order_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('computer_course_semesters');
    }
};
