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
        Schema::table('students', function (Blueprint $table) {
            // Carried across from the admission application on approval.
            // Existing students default to not accepted.
            $table->boolean('instructions_accepted')->default(false)->after('notes');
            $table->timestamp('instructions_accepted_at')->nullable()->after('instructions_accepted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['instructions_accepted', 'instructions_accepted_at']);
        });
    }
};
