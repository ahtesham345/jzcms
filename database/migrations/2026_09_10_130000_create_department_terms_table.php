<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The instructions a guardian agrees to, per department.
     *
     * One row per department, which is why department_id is unique: a
     * department has one set of instructions, not a history of them. The
     * combined student types have no row and never will - Hifz + School is
     * two placements, so its guardian is shown the two departments' sets
     * together rather than a third set of its own.
     *
     * The items are kept as text, one instruction per line, because that is
     * exactly how the admin enters them. Storing the block verbatim means
     * the Urdu comes back out as it went in, and an administrator reading
     * the column directly sees the instructions rather than an encoding of
     * them.
     *
     * The heading and the agreement sentence are deliberately absent. Those
     * are the institution's, identical for every department, and stay in
     * config where they already are.
     */
    public function up(): void
    {
        Schema::create('department_terms', function (Blueprint $table) {
            $table->id();

            // One set per department. Deleting a department takes its
            // instructions with it: they describe that programme and mean
            // nothing without it.
            $table->foreignId('department_id')
                ->unique()
                ->constrained('departments')
                ->cascadeOnDelete();

            $table->text('items')->nullable();

            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_terms');
    }
};
