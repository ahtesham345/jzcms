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
        Schema::create('parent_student', function (Blueprint $table) {
            $table->id();

            // Removing a parent or a student removes the link itself; the
            // link has no meaning without both sides. Nothing else about
            // either record is touched.
            $table->foreignId('parent_id')
                ->constrained('parents')
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->enum('relationship_type', ['Father', 'Mother', 'Guardian']);

            // At most one primary per relationship type per student. That
            // rule is enforced in validation and again inside the locked
            // transaction in ParentGuardian::linkStudent(): a partial unique
            // index ("... where is_primary = 1") is not portable to MySQL.
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            // The same parent cannot be linked to the same student twice.
            $table->unique(['parent_id', 'student_id'], 'parent_student_unique');

            // The show pages read the links from both directions.
            $table->index(['student_id', 'relationship_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parent_student');
    }
};
