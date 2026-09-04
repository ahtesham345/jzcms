<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Map the retired statuses onto their closest replacement before the
        // column stops accepting them.
        DB::table('admission_applications')
            ->where('status', 'Interview Scheduled')
            ->update(['status' => 'Test Scheduled']);

        DB::table('admission_applications')
            ->where('status', 'Waiting List')
            ->update(['status' => 'Under Review']);

        Schema::table('admission_applications', function (Blueprint $table) {
            $table->enum('status', [
                'Pending',
                'Under Review',
                'Test Scheduled',
                'Test Completed',
                'Passed',
                'Failed',
                'Approved',
                'Rejected',
            ])->default('Pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Collapse the statuses that did not exist before onto the old set.
        DB::table('admission_applications')
            ->whereIn('status', ['Test Scheduled', 'Test Completed'])
            ->update(['status' => 'Interview Scheduled']);

        DB::table('admission_applications')
            ->where('status', 'Under Review')
            ->update(['status' => 'Waiting List']);

        DB::table('admission_applications')
            ->where('status', 'Passed')
            ->update(['status' => 'Approved']);

        DB::table('admission_applications')
            ->where('status', 'Failed')
            ->update(['status' => 'Rejected']);

        Schema::table('admission_applications', function (Blueprint $table) {
            $table->enum('status', [
                'Pending',
                'Approved',
                'Rejected',
                'Interview Scheduled',
                'Waiting List',
            ])->default('Pending')->change();
        });
    }
};
