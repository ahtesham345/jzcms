<?php

namespace App\Console\Commands;

use App\Models\AdmissionApplication;
use App\Models\Student;
use App\Support\AcademicPlacement;
use Illuminate\Console\Command;

/**
 * Lists students whose student type includes Computer but who hold no
 * Computer placement.
 *
 * These are the students admitted while Computer was still treated as an
 * extra facility rather than a department: the type on their record says
 * Dars-e-Nizami + Computer, but only the Dars-e-Nizami enrollment was ever
 * written.
 *
 * This command reports and never writes. Giving them a Computer placement
 * means choosing a semester and a start date, and nobody knows either: a
 * student two years into the course would be recorded as starting the first
 * semester today, which is not a gap being filled but false academic history
 * being created. So the decision is left with the office, one student at a
 * time, through the student's own edit form.
 */
class ReportMissingComputerPlacements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'computer:missing-placements';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List students whose student type includes Computer but who have no Computer placement';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $track = AcademicPlacement::track(AcademicPlacement::SEMESTER_SIDE);

        // Every student type the mapping places in the Computer department.
        $types = collect(AdmissionApplication::STUDENT_TYPE_DEPARTMENTS)
            ->filter(fn (array $sides) => array_key_exists(AcademicPlacement::SEMESTER_SIDE, $sides))
            ->keys();

        if ($types->isEmpty()) {
            $this->info('No student type is placed in the Computer department.');

            return self::SUCCESS;
        }

        $students = Student::query()
            ->whereIn('student_type', $types)
            ->whereDoesntHave('academicEnrollments', fn ($enrollment) => $enrollment->where('academic_track', $track))
            ->orderBy('registration_number')
            ->get(['id', 'registration_number', 'full_name', 'student_type', 'student_status']);

        if ($students->isEmpty()) {
            $this->info('Every Computer student already has a Computer placement.');

            return self::SUCCESS;
        }

        $this->warn("{$students->count()} student(s) have a Computer student type but no Computer placement:");
        $this->newLine();

        $this->table(
            ['Reg. No', 'Name', 'Student Type', 'Status'],
            $students->map(fn (Student $student) => [
                $student->registration_number,
                $student->full_name,
                $student->student_type,
                $student->student_status,
            ])->all()
        );

        $this->newLine();
        $this->line('Nothing has been changed. Each of these needs a semester and a start date');
        $this->line('that only the office knows, so add the placement from the student\'s own');
        $this->line('edit form, or through Academic Records, rather than in bulk.');

        return self::SUCCESS;
    }
}
