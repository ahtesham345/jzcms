<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gives students admitted before academic history existed one enrollment
 * built from their current placement fields.
 *
 * Deliberately conservative: it only touches students that have no
 * enrollment row at all, and only when every field it needs is present. It
 * never edits an existing enrollment and never guesses a missing value.
 *
 * Runs as a dry run unless --commit is passed.
 */
class BackfillStudentEnrollments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'enrollments:backfill
                            {--commit : Write the enrollments. Without this the command only reports what it would do.}
                            {--track=Madrassa : The academic track to record, for students whose type does not imply one.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an academic enrollment for students that have none, from their current placement';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $track = $this->option('track');

        if (! in_array($track, StudentAcademicEnrollment::ACADEMIC_TRACKS, true)) {
            $this->error("Unknown track [{$track}]. Use one of: ".implode(', ', StudentAcademicEnrollment::ACADEMIC_TRACKS));

            return self::FAILURE;
        }

        $candidates = Student::doesntHave('academicEnrollments')->get();

        if ($candidates->isEmpty()) {
            $this->info('Every student already has at least one enrollment. Nothing to do.');

            return self::SUCCESS;
        }

        [$ready, $skipped] = $candidates->partition(fn (Student $student) => $this->hasEnoughData($student));

        $this->table(
            ['Registration', 'Name', 'Session', 'Department', 'Class', 'Section', 'Action'],
            $candidates->map(fn (Student $student) => [
                $student->registration_number,
                $student->full_name,
                $student->academic_session_id ?? '—',
                $student->department_id ?? '—',
                $student->academic_class_id ?? '—',
                $student->section_id ?? 'none',
                $this->hasEnoughData($student) ? 'backfill' : 'SKIP (incomplete placement)',
            ])->all()
        );

        $this->line('');
        $this->info("Students with no enrollment: {$candidates->count()}");
        $this->info("Ready to backfill:           {$ready->count()}");
        $this->info("Skipped as incomplete:       {$skipped->count()}");

        if (! $this->option('commit')) {
            $this->line('');
            $this->warn('Dry run. Nothing was written. Re-run with --commit to apply.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($ready, $track) {
            foreach ($ready as $student) {
                $student->academicEnrollments()->create([
                    'academic_session_id' => $student->academic_session_id,
                    'academic_track' => $track,
                    'department_id' => $student->department_id,
                    'academic_class_id' => $student->academic_class_id,
                    'section_id' => $student->section_id,
                    // The admission date is the closest thing on record to
                    // when this placement began.
                    'start_date' => $student->admission_date,
                    'status' => $student->student_status === 'Active' ? 'Active' : 'Completed',
                    'notes' => 'Backfilled from the student record.',
                ]);
            }
        });

        $this->info("Backfilled {$ready->count()} enrollment(s).");

        return self::SUCCESS;
    }

    /**
     * Determine whether a student's placement is complete enough to record.
     */
    private function hasEnoughData(Student $student): bool
    {
        return $student->academic_session_id !== null
            && $student->department_id !== null
            && $student->academic_class_id !== null
            && $student->admission_date !== null;
    }
}
