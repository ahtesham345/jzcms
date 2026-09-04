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
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();

            // Entered by the admin: the project has no employee/teacher ID
            // generator, so uniqueness is enforced here and in validation.
            $table->string('teacher_id')->unique();

            // Path on the public disk, never the image itself.
            $table->string('photo')->nullable();

            $table->string('full_name');
            $table->string('father_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['Male', 'Female']);
            $table->string('cnic_number')->nullable();

            $table->string('mobile_number');
            $table->string('alternate_mobile')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();

            $table->string('qualification')->nullable();
            $table->string('specialization')->nullable();
            $table->date('joining_date');

            $table->enum('teacher_status', ['Active', 'Inactive'])->default('Active');

            $table->text('notes')->nullable();

            $table->timestamps();

            // The list is filtered by status and searched by name/mobile.
            $table->index('teacher_status');
            $table->index('full_name');
            $table->index('mobile_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
