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
            // Existing applications default to not accepted and are never
            // required to accept retroactively.
            $table->boolean('instructions_accepted')->default(false)->after('notes');
            $table->timestamp('instructions_accepted_at')->nullable()->after('instructions_accepted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->dropColumn(['instructions_accepted', 'instructions_accepted_at']);
        });
    }
};
