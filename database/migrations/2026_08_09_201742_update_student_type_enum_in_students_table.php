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
        // Drop the old student_type column and recreate with new values
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('student_type');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->enum('student_type', ['Hifz', 'Hifz + School', 'School', 'Dars-e-Nizami + Computer', 'Dars-e-Nizami'])->after('student_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('student_type');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->enum('student_type', ['Madrassa', 'School'])->after('student_status');
        });
    }
};
