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
        Schema::table('admission_applications', function (Blueprint $table) {
            // The madrassa side (Hifz or Dars-e-Nizami) and the school side are
            // stored separately so a Hifz + School applicant can hold both.
            $table->foreignId('madrassa_class_id')
                ->nullable()
                ->after('student_type')
                ->constrained('academic_classes')
                ->nullOnDelete();

            $table->foreignId('school_class_id')
                ->nullable()
                ->after('madrassa_class_id')
                ->constrained('academic_classes')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->dropForeign(['madrassa_class_id']);
            $table->dropForeign(['school_class_id']);
            $table->dropColumn(['madrassa_class_id', 'school_class_id']);
        });
    }
};
