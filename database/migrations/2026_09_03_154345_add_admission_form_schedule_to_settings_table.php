<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * When the public admission form is reachable. Three columns on the
     * existing singleton settings row rather than a table of their own:
     * this is institution configuration, which is what that row is for.
     *
     * Two datetimes, not four date-and-time pairs. The form asks for a date
     * and a time separately because that is easier to fill in, but "when
     * admissions open" is one instant and is stored as one, so nothing
     * downstream has to recombine a pair before it can compare them.
     *
     * The default is off. An institution that has never configured a window
     * has not decided to accept applications, and a schedule that defaulted
     * to open would leave the public form permanently reachable on every
     * installation that never visited the page.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('admission_form_enabled')
                ->default(false)
                ->after('date_format');

            // Nullable because "enabled" and "scheduled" are separate
            // states: the toggle can be switched off with a window still
            // saved, and a window can be half filled in while being edited.
            // The open check requires both to be present, so a missing one
            // means closed rather than unbounded.
            $table->dateTime('admission_form_opens_at')
                ->nullable()
                ->after('admission_form_enabled');

            $table->dateTime('admission_form_closes_at')
                ->nullable()
                ->after('admission_form_opens_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'admission_form_enabled',
                'admission_form_opens_at',
                'admission_form_closes_at',
            ]);
        });
    }
};
