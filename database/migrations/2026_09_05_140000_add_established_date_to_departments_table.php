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
        Schema::table('departments', function (Blueprint $table) {
            // When the department first opened, as a historical fact about
            // the institution. Deliberately not tied to an academic session:
            // Hifz started in 1995 whatever year the school is running now.
            //
            // Nullable, and left null for every department already on file.
            // Nobody knows these dates but the Imam, so the column waits for
            // him rather than being filled with a guess.
            $table->date('established_date')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('established_date');
        });
    }
};
