<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The tracks the column accepts once Computer is a department.
     *
     * @var array<int, string>
     */
    private array $tracks = ['Madrassa', 'School', 'Computer'];

    /**
     * The tracks it accepted before.
     *
     * @var array<int, string>
     */
    private array $previous = ['Madrassa', 'School'];

    /**
     * Run the migrations.
     *
     * Computer becomes a third academic track, so a Dars-e-Nizami + Computer
     * student can hold one enrollment per programme the way a Hifz + School
     * student already does.
     *
     * The value is appended rather than the column being rebuilt. MariaDB
     * widens an enum in place when the new value goes on the end and the
     * list still fits one byte, so no row is rewritten and nothing already
     * recorded is touched. The students table's own student_type enum was
     * once changed by dropping and re-adding the column; that is not done
     * here, because dropping a populated column discards what it held.
     */
    public function up(): void
    {
        $this->setTracks($this->tracks);
    }

    /**
     * Reverse the migrations.
     *
     * Refused while any enrollment is still on the Computer track: narrowing
     * the enum under those rows would silently empty their track and leave
     * placements that belong to nothing.
     */
    public function down(): void
    {
        if (DB::table('student_academic_enrollments')->where('academic_track', 'Computer')->exists()) {
            throw new RuntimeException(
                'Computer enrollments exist. Remove or re-track them before narrowing academic_track.'
            );
        }

        $this->setTracks($this->previous);
    }

    /**
     * Set the values the academic_track column accepts.
     *
     * Two drivers, because the two spell an enum differently: MySQL and
     * MariaDB have a real ENUM type, and SQLite - which the test suite runs
     * on - stores it as a varchar with a check constraint, which Laravel
     * rewrites by rebuilding the table.
     *
     * @param  array<int, string>  $values
     */
    private function setTracks(array $values): void
    {
        if (DB::getDriverName() === 'mysql') {
            $list = implode(', ', array_map(fn (string $value) => "'".$value."'", $values));

            DB::statement(
                "ALTER TABLE `student_academic_enrollments` MODIFY `academic_track` ENUM({$list}) NOT NULL"
            );

            return;
        }

        Schema::table('student_academic_enrollments', function (Blueprint $table) use ($values) {
            $table->enum('academic_track', $values)->change();
        });
    }
};
