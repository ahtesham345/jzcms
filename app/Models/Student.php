<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Student extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'registration_number',
        'roll_number',
        'photo',
        'full_name',
        'father_name',
        'date_of_birth',
        'gender',
        'b_form_number',
        'permanent_address',
        'current_address',
        'father_mobile',
        'mother_mobile',
        'emergency_contact',
        'admission_date',
        'academic_session_id',
        'department_id',
        'academic_class_id',
        'section_id',
        'student_status',
        'leaving_reason',
        'student_type',
        'resident_type',
        'medical_information',
        'notes',
        'instructions_accepted',
        'instructions_accepted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'admission_date' => 'date',
            'instructions_accepted' => 'boolean',
            'instructions_accepted_at' => 'datetime',
        ];
    }

    /**
     * Get the academic enrollments recorded for this student.
     */
    public function academicEnrollments()
    {
        return $this->hasMany(StudentAcademicEnrollment::class);
    }

    /**
     * Get the student's current academic enrollment.
     *
     * A dual-track student holds one active enrollment per track, so this
     * returns the most recently started of them. Use activeAcademicEnrollments()
     * when every current placement is wanted.
     */
    public function activeAcademicEnrollment()
    {
        return $this->hasOne(StudentAcademicEnrollment::class)
            ->where('status', 'Active')
            ->latest('start_date');
    }

    /**
     * Get every current academic enrollment, one per track at most.
     */
    public function activeAcademicEnrollments()
    {
        return $this->hasMany(StudentAcademicEnrollment::class)
            ->where('status', 'Active')
            ->orderBy('academic_track');
    }

    /**
     * Get the student's active enrollment on one track, if any.
     */
    public function activeEnrollmentForTrack(string $track): ?StudentAcademicEnrollment
    {
        return $this->academicEnrollments()
            ->where('academic_track', $track)
            ->where('status', 'Active')
            ->first();
    }

    /**
     * Determine whether this student may be promoted.
     *
     * Only a student still attending. A record marked Passed or Left has
     * reached an outcome, and moving it into a new class would contradict
     * that. Nothing academic is judged here: marks, exams and attendance
     * are separate modules.
     */
    public function canBePromoted(): bool
    {
        return $this->student_status === 'Active';
    }

    /**
     * Determine whether the current placement columns represent a track.
     *
     * The students table holds one placement, but a Hifz + School student
     * holds two active enrollments. The admission flow already resolved
     * this by recording the madrassa side on the student row, so the row
     * is treated as belonging to whichever track it currently matches, and
     * to the only track there is when the student has just one.
     */
    public function placementRepresentsTrack(string $track): bool
    {
        $active = $this->academicEnrollments()->where('status', 'Active')->get();

        if ($active->where('academic_track', '!=', $track)->isEmpty()) {
            return true;
        }

        $enrollment = $active->firstWhere('academic_track', $track);

        return $enrollment !== null
            && (int) $enrollment->department_id === (int) $this->department_id
            && (int) $enrollment->academic_class_id === (int) $this->academic_class_id;
    }

    /**
     * Refuse a second enrollment on a track that allows one per session.
     *
     * The school runs a class for the academic year, so a student holds one
     * school enrollment per session. The madrassa does not: a stage finishes
     * when the student finishes it, so a madrassa student may be enrolled
     * into Hifz in the same session their Nazra enrollment belongs to.
     *
     * This is the whole of that rule. It is not a unique index because a
     * unique index cannot be made conditional on a column value in a way
     * that is portable to MySQL - the same reason
     * ParentGuardian::linkStudent() re-checks "one primary per relationship
     * type" in PHP. The row lock is what makes it hold under concurrent
     * writes, so every caller must run it inside a transaction: the read and
     * the insert it guards have to be one atomic step, or two requests can
     * both read "free" and both insert.
     *
     * Shared by promote() and addEnrollment(), which are the two ways a new
     * enrollment reaches the table from a form.
     *
     * @throws ValidationException
     */
    private function guardSessionBoundTrack(string $track, int $academicSessionId): void
    {
        if (! StudentAcademicEnrollment::trackIsSessionBound($track)) {
            return;
        }

        $taken = $this->academicEnrollments()
            ->where('academic_track', $track)
            ->where('academic_session_id', $academicSessionId)
            ->lockForUpdate()
            ->first();

        if ($taken !== null) {
            // The same key and wording the enrollment form's own unique rule
            // uses, so a request that loses this race is reported to the
            // admin exactly as one that failed validation outright.
            throw ValidationException::withMessages([
                'academic_session_id' => 'This student already has an enrollment for that session and track.',
            ]);
        }
    }

    /**
     * Record one academic placement for this student.
     *
     * The check and the insert share a transaction, with the rows the check
     * reads locked for its duration. Validation has already run by the time
     * this is called, but it ran outside any transaction and against an
     * earlier moment: two school enrollments submitted at once could both
     * pass it. This is what stops the second being written.
     *
     * The madrassa path is deliberately untouched by the guard - several
     * placements in one session are legitimate there - so nothing about it
     * is serialised beyond the insert itself.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function addEnrollment(array $data): StudentAcademicEnrollment
    {
        return DB::transaction(function () use ($data) {
            $this->guardSessionBoundTrack(
                $data['academic_track'],
                (int) $data['academic_session_id']
            );

            return $this->academicEnrollments()->create($data);
        });
    }

    /**
     * Promote one track of this student's placement.
     *
     * The active enrollment for the track is completed as at the promotion
     * date and a new active one is opened in the target session, all in one
     * transaction: the history can never be closed without its replacement
     * being written, nor the placement moved without both. The enrollment
     * rows are locked first so two concurrent promotions cannot both read
     * the same active enrollment and complete it twice.
     *
     * The old row is never edited beyond its status and completion date,
     * and never deleted: it stays in the history exactly as recorded.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function promote(array $data): StudentAcademicEnrollment
    {
        return DB::transaction(function () use ($data) {
            $track = $data['academic_track'];

            $current = $this->academicEnrollments()
                ->where('academic_track', $track)
                ->where('status', 'Active')
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                throw ValidationException::withMessages([
                    'academic_track' => "This student has no active {$track} enrollment to promote from.",
                ]);
            }

            // Decided before anything moves: once the old enrollment is
            // completed and the new one written, the placement columns no
            // longer match the track they came from.
            $updatePlacement = $this->placementRepresentsTrack($track);

            // Re-checked under the lock: validation saw an earlier moment.
            $this->guardSessionBoundTrack($track, (int) $data['academic_session_id']);

            $current->update([
                'status' => 'Completed',
                'end_date' => $data['promotion_date'],
            ]);

            $promoted = $this->academicEnrollments()->create([
                'academic_session_id' => $data['academic_session_id'],
                'academic_track' => $track,
                'department_id' => $data['department_id'],
                'academic_class_id' => $data['academic_class_id'],
                'section_id' => $data['section_id'] ?? null,
                'start_date' => $data['promotion_date'],
                'status' => 'Active',
                'notes' => $data['notes'] ?? null,
            ]);

            // Only when this track is the one the student row stands for.
            // A dual-track student's other track keeps its own history and
            // is untouched by this promotion.
            if ($updatePlacement) {
                $this->update([
                    'academic_session_id' => $promoted->academic_session_id,
                    'department_id' => $promoted->department_id,
                    'academic_class_id' => $promoted->academic_class_id,
                    'section_id' => $promoted->section_id,
                ]);
            }

            return $promoted;
        });
    }

    /**
     * Get the admission application this student was admitted from.
     */
    public function admissionApplication()
    {
        return $this->hasOne(AdmissionApplication::class);
    }

    /**
     * Get the academic session that the student belongs to.
     */
    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * Get the department that the student belongs to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the academic class that the student belongs to.
     */
    public function academicClass()
    {
        return $this->belongsTo(AcademicClass::class);
    }

    /**
     * Get the section that the student belongs to.
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Get the discipline records recorded against this student.
     *
     * Attached to the student rather than to an enrollment, which is what
     * keeps an incident on file after the student is promoted into another
     * class, section or session. Listings and summaries go through the
     * DisciplineRecord query rather than loading this relation, so counting
     * a student's incidents never means loading them.
     */
    public function disciplineRecords()
    {
        return $this->hasMany(DisciplineRecord::class);
    }

    /**
     * Get the parents/guardians linked to this student.
     *
     * Separate from the father_name/father_mobile/mother_mobile fields on
     * this table, which are left as they are for now.
     */
    public function parents()
    {
        return $this->belongsToMany(ParentGuardian::class, 'parent_student', 'student_id', 'parent_id')
            ->using(ParentStudent::class)
            ->withPivot(['id', 'relationship_type', 'is_primary'])
            ->withTimestamps();
    }
}
