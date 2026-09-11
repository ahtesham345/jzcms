<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The Computer department's curriculum structure: a course of a stated
     * length, divided into the semesters recorded in the table beside this
     * one.
     *
     * Deliberately not tied to an academic session. A three-year course runs
     * across about three of them, and the institution's session is the
     * school year rather than the length of a curriculum, so pinning the
     * course to one would either be false or would need a three-year session
     * invented to hold it. The semesters carry their own dates instead.
     */
    public function up(): void
    {
        Schema::create('computer_courses', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            // Stated in years and semesters because that is how the course is
            // described - three years, six semesters - rather than derived
            // from the semester rows, which the admin dates one at a time and
            // may still be filling in.
            $table->unsignedTinyInteger('duration_years');
            $table->unsignedTinyInteger('semester_count');

            $table->text('description')->nullable();

            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('computer_courses');
    }
};
