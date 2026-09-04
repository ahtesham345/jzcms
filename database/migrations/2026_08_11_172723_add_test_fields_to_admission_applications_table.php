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
            $table->date('test_date')->nullable()->after('status');
            $table->time('test_time')->nullable()->after('test_date');
            $table->decimal('test_marks', 5, 2)->nullable()->after('test_time');
            $table->enum('test_result', ['Passed', 'Failed'])->nullable()->after('test_marks');
            $table->text('test_remarks')->nullable()->after('test_result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->dropColumn([
                'test_date',
                'test_time',
                'test_marks',
                'test_result',
                'test_remarks',
            ]);
        });
    }
};
