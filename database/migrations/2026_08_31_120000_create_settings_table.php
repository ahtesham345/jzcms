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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            // ---- Institution -------------------------------------------
            //
            // The Urdu columns sit beside their English counterparts rather
            // than in a translations table. There are exactly two languages
            // in this project and the report layer already knows both by
            // name, so a second table would buy nothing and cost a join on
            // every read.
            //
            // Only the four fields a printed document actually needs have
            // an Urdu twin. A phone number, an email address and a URL read
            // the same in either script.
            $table->string('institution_name');
            $table->string('institution_name_urdu')->nullable();

            // The path on the public disk, never the file itself. Same
            // convention as students.photo and parents.photo.
            $table->string('logo')->nullable();

            $table->text('address');
            $table->text('address_urdu')->nullable();

            $table->string('phone_number');
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            $table->string('principal_name');
            $table->string('principal_name_urdu')->nullable();

            $table->string('tagline')->nullable();
            $table->string('tagline_urdu')->nullable();

            // ---- System preferences ------------------------------------
            //
            // The language is stored as the code the report layer already
            // uses - 'en' or 'ur' - not as the word "English". A second
            // vocabulary for the same two languages is exactly the
            // duplication that leaves a default preference unable to speak
            // to the PDF that is supposed to honour it.
            $table->string('default_language', 5)->default('en');

            // An IANA identifier, validated against the list PHP itself
            // ships. Stored rather than derived from config so the
            // institution can set it without an env file.
            $table->string('timezone')->default('Asia/Karachi');

            // A PHP date() format string, restricted to the handful the
            // Settings page offers. Stored for future centralised use: this
            // chunk deliberately does not rewrite the date output of every
            // existing module, because there is no central formatter yet to
            // rewrite them through.
            $table->string('date_format')->default('d M, Y');

            $table->timestamps();

            // No unique index and no guard column. There is one row because
            // there is no route, controller action or form that can create
            // a second one - the Settings page has no id in its URL - and
            // the model refuses a second create() outright. A constraint
            // here would be a third statement of the same rule.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
