<?php

namespace Tests\Feature\Concerns;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\MadrassaDailyRecord;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\AdmissionDepartmentClassSeeder;

/**
 * The academic structure the daily record tests are written against.
 *
 * Shared by the record tests and the roster tests so both are describing
 * one madrassa rather than two that happen to look alike. Nothing here
 * asserts anything: it builds sessions, departments, classes, sections,
 * students and enrollments, and leaves the judging to the tests.
 */
trait BuildsMadrassaFixtures
{
    /** August 2026: Mondays fall on the 3rd, weekends on the 1st and 2nd. */
    protected const MONDAY = '2026-08-03';

    protected const TUESDAY = '2026-08-04';

    protected const WEDNESDAY = '2026-08-05';

    protected const THURSDAY = '2026-08-06';

    protected const FRIDAY = '2026-08-07';

    protected const SATURDAY = '2026-08-01';

    protected const SUNDAY = '2026-08-02';

    protected AcademicSession $session;

    protected AcademicSession $nextSession;

    protected Department $hifz;

    protected Department $school;

    protected Department $darsENizami;

    protected AcademicClass $hifzClass;

    protected AcademicClass $nazra;

    protected AcademicClass $primary;

    protected AcademicClass $salEAwwal;

    protected AcademicClass $salEDom;

    protected Section $hifzA;

    protected Section $hifzB;

    protected Section $primaryB;

    /**
     * Build the structure and sign in as an administrator.
     */
    protected function buildMadrassa(): void
    {
        $this->actingAs(User::factory()->create());
        $this->seed(AdmissionDepartmentClassSeeder::class);

        $this->session = AcademicSession::create([
            'name' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'is_current' => true, 'status' => true,
        ]);
        $this->nextSession = AcademicSession::create([
            'name' => '2027-2028', 'start_date' => '2027-04-01', 'end_date' => '2028-03-31',
            'status' => true,
        ]);

        $this->hifz = Department::where('name', 'Hifz')->firstOrFail();
        $this->school = Department::where('name', 'School')->firstOrFail();
        $this->darsENizami = Department::where('name', 'Dars-e-Nizami')->firstOrFail();

        $this->hifzClass = AcademicClass::where('department_id', $this->hifz->id)->where('name', 'Hifz')->firstOrFail();
        $this->nazra = AcademicClass::where('department_id', $this->hifz->id)->where('name', 'Nazra')->firstOrFail();
        $this->primary = AcademicClass::where('department_id', $this->school->id)->where('name', 'Primary Section')->firstOrFail();

        $darsClasses = AcademicClass::where('department_id', $this->darsENizami->id)->orderBy('id')->get();
        $this->salEAwwal = $darsClasses->first();
        $this->salEDom = $darsClasses->get(1) ?? $darsClasses->first();

        $this->hifzA = $this->section('Hifz-A', $this->hifzClass);
        $this->hifzB = $this->section('Hifz-B', $this->hifzClass);
        $this->primaryB = $this->section('Primary-B', $this->primary);
    }

    protected function section(string $name, AcademicClass $class): Section
    {
        return Section::create([
            'name' => $name,
            'code' => strtoupper(str_replace('-', '', $name)),
            'academic_class_id' => $class->id,
            'status' => true,
        ]);
    }

    /**
     * Create a student, numbered so registration numbers stay unique.
     */
    protected function student(string $name = 'Ahtesham Shakeel', string $studentType = 'Hifz'): Student
    {
        static $sequence = 0;
        $sequence++;

        return Student::create([
            'registration_number' => 'STD-2026-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'roll_number' => (string) $sequence,
            'full_name' => $name,
            'father_name' => 'Muhammad Shakeel',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'emergency_contact' => '03007654321',
            'admission_date' => '2026-04-01',
            'academic_session_id' => $this->session->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
            'student_status' => 'Active',
            'student_type' => $studentType,
            'resident_type' => 'Local Resident',
        ]);
    }

    /**
     * Enroll a student on the madrassa track.
     */
    protected function madrassaEnrollment(?Student $student = null, array $overrides = []): StudentAcademicEnrollment
    {
        $student ??= $this->student();

        return $student->academicEnrollments()->create(array_merge([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
            'start_date' => '2026-04-01',
            'status' => 'Active',
        ], $overrides));
    }

    /**
     * Enroll a student on the school track.
     */
    protected function schoolEnrollment(?Student $student = null, array $overrides = []): StudentAcademicEnrollment
    {
        $student ??= $this->student();

        return $student->academicEnrollments()->create(array_merge([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
            'section_id' => $this->primaryB->id,
            'start_date' => '2026-04-01',
            'status' => 'Active',
        ], $overrides));
    }

    /**
     * Enroll a Dars-e-Nizami student on the madrassa track.
     */
    protected function darsEnrollment(?Student $student = null, array $overrides = []): StudentAcademicEnrollment
    {
        $student ??= $this->student('Bilal Ahmad', 'Dars-e-Nizami');

        return $student->academicEnrollments()->create(array_merge([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->darsENizami->id,
            'academic_class_id' => $this->salEAwwal->id,
            'section_id' => null,
            'start_date' => '2026-04-01',
            'status' => 'Active',
        ], $overrides));
    }

    protected function teacher(string $status = 'Active', string $name = 'Qari Abdul Rahman'): Teacher
    {
        return Teacher::createWithTeacherId([
            'full_name' => $name,
            'gender' => 'Male',
            'mobile_number' => '03001112222',
            'joining_date' => '2026-01-01',
            'teacher_status' => $status,
        ]);
    }

    /**
     * A complete Hifz submission for one enrollment.
     */
    protected function hifzPayload(StudentAcademicEnrollment $enrollment, array $overrides = []): array
    {
        return array_merge([
            'student_id' => $enrollment->student_id,
            'student_academic_enrollment_id' => $enrollment->id,
            'record_date' => self::MONDAY,
            'sabaq' => 'Surah Al-Baqarah, from ayah 1',
            'sabaq_quantity' => '1 page',
            'sabqi' => 'Para 3',
            'sabqi_quantity' => '3 pages',
            'manzil' => 'Para 1 to 5',
            'manzil_quantity' => '1 para',
            'next_sabaq' => 'Surah Al-Baqarah, ayah 20 onwards',
            'remarks' => 'Good performance',
        ], $overrides);
    }

    /**
     * A complete Dars-e-Nizami submission for one enrollment.
     */
    protected function darsPayload(StudentAcademicEnrollment $enrollment, array $overrides = []): array
    {
        return array_merge([
            'student_id' => $enrollment->student_id,
            'student_academic_enrollment_id' => $enrollment->id,
            'record_date' => self::MONDAY,
            'subject_book' => 'Nahw Mir',
            'todays_lesson' => 'Lesson 12',
            'lesson_topic_covered' => 'Murakkab Naqis',
            'revision' => 'Lessons 9 to 11',
            'next_lesson' => 'Lesson 13',
            'remarks' => 'Attentive',
        ], $overrides);
    }

    /**
     * Write a record straight to the table, bypassing the form.
     */
    protected function record(StudentAcademicEnrollment $enrollment, array $overrides = []): MadrassaDailyRecord
    {
        $type = MadrassaDailyRecord::recordTypeForEnrollment($enrollment->loadMissing('student'));

        $work = $type === MadrassaDailyRecord::TYPE_HIFZ
            ? ['sabaq' => 'Surah Al-Baqarah', 'sabaq_quantity' => '1 page']
            : ['subject_book' => 'Nahw Mir', 'todays_lesson' => 'Lesson 12'];

        return MadrassaDailyRecord::create(array_merge([
            'student_academic_enrollment_id' => $enrollment->id,
            'record_date' => self::MONDAY,
            'record_type' => $type,
        ], $work, $overrides));
    }
}
