<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveAdmissionApplicationRequest;
use App\Http\Requests\Admin\StoreAdmissionApplicationRequest;
use App\Http\Requests\Admin\UpdateAdmissionApplicationRequest;
use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Department;
use App\Models\ParentGuardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Support\AdmissionApplicationFilters;
use App\Support\ResultReportLanguage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdmissionApplicationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = AdmissionApplicationFilters::fromRequest($request);

        $applications = AdmissionApplication::query()
            ->filter($filters)
            // The listing shows the department and the class applied for, so
            // both sides are loaded up front rather than one pair of queries
            // per row.
            ->with(['academicSession', 'madrassaClass.department', 'schoolClass.department'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admissions.index', [
            'applications' => $applications,
            'filters' => $filters,
            // What the Print Passed Students link carries: the filters the
            // page is showing, minus the empties, so the notice covers
            // exactly these applicants.
            'pdfFilters' => array_filter($filters, fn ($value) => $value !== null && $value !== ''),
            'reportLanguages' => ResultReportLanguage::NAMES,
        ] + AdmissionApplicationFilters::options());
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admissions.create', [
            // Stamped automatically on save. Shown here so the admin knows
            // which year they are filing into, and warned about when there is
            // no session to file into at all.
            'academicSession' => AcademicSession::current(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAdmissionApplicationRequest $request)
    {
        $application = AdmissionApplication::createWithApplicationNumber($request->validated());

        return redirect()
            ->route('admissions.index')
            ->with('success', "Admission application {$application->application_number} created successfully.");
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $application = AdmissionApplication::with([
            'student',
            'madrassaClass.department',
            'schoolClass.department',
        ])->findOrFail($id);

        // Only loaded when an approval is actually possible, so the show page
        // does not pay for the extra queries on every view.
        $academicSessions = $departments = $academicClasses = collect();
        $madrassaSections = $schoolSections = collect();
        $sectionsByClass = [];

        if ($application->canBeApproved()) {
            $academicSessions = AcademicSession::where('status', true)->orderBy('name')->get();

            // Sections are scoped to the class the application actually chose,
            // never loaded globally.
            $madrassaSections = $this->sectionsForClass($application->madrassa_class_id);
            $schoolSections = $this->sectionsForClass($application->school_class_id);

            // Department and class are pre-filled from the application, so
            // these lists are only needed by applications that carry none.
            if ($application->madrassa_class_id === null && $application->school_class_id === null) {
                $departments = Department::where('status', true)->orderBy('name')->get();
                $academicClasses = AcademicClass::where('status', true)->orderBy('name')->get();

                // The class is picked in the modal, so the sections of each
                // class are sent along for the dropdown to filter on.
                $sectionsByClass = Section::where('status', true)
                    ->orderBy('name')
                    ->get()
                    ->groupBy('academic_class_id')
                    ->map(fn ($sections) => $sections->map(fn ($section) => [
                        'id' => $section->id,
                        'name' => $section->name,
                    ])->values()->all())
                    ->all();
            }
        }

        return view('admissions.show', compact(
            'application',
            'academicSessions',
            'departments',
            'academicClasses',
            'madrassaSections',
            'schoolSections',
            'sectionsByClass'
        ));
    }

    /**
     * Get the active sections belonging to one class.
     */
    private function sectionsForClass(?int $academicClassId)
    {
        if ($academicClassId === null) {
            return collect();
        }

        return Section::where('status', true)
            ->where('academic_class_id', $academicClassId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Approve the application and create the corresponding student.
     */
    public function approve(ApproveAdmissionApplicationRequest $request, string $id)
    {
        $application = AdmissionApplication::with(['madrassaClass', 'schoolClass'])->findOrFail($id);

        // Guard against a second approval, including a double-submitted form.
        if ($application->isApproved()) {
            return redirect()
                ->route('admissions.show', $application->id)
                ->with('error', 'This application has already been approved.');
        }

        if (! $application->canBeApproved()) {
            return redirect()
                ->route('admissions.show', $application->id)
                ->with('error', 'Only applications with a status and test result of Passed can be approved.');
        }

        $student = $this->approveWithStudent($application, $request->validated());

        return redirect()
            ->route('admissions.show', $application->id)
            ->with('success', "Admission approved. Student {$student->full_name} created with registration number {$student->registration_number}.");
    }

    /**
     * Create the student and mark the application approved in one transaction.
     *
     * Both writes succeed together or neither happens, so a failure while
     * creating the student can never leave the application marked Approved.
     *
     * @param  array<string, mixed>  $placement
     */
    private function approveWithStudent(AdmissionApplication $application, array $placement): Student
    {
        $attempts = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($application, $placement) {
                    // Department and class come from the application itself.
                    // Only an application with no class of its own falls back
                    // to the placement chosen during approval.
                    $primaryClass = $application->madrassaClass ?? $application->schoolClass;

                    $student = Student::create([
                        'registration_number' => $this->nextRegistrationNumber(),
                        'roll_number' => $placement['roll_number'] ?? null,
                        // The already stored path is reused: the file itself
                        // is never copied or uploaded again.
                        'photo' => $application->photo,
                        'full_name' => $application->student_name,
                        'father_name' => $application->father_name,
                        'date_of_birth' => $application->date_of_birth,
                        'gender' => $application->gender,
                        'b_form_number' => $application->b_form_number,
                        'permanent_address' => $application->permanent_address,
                        'current_address' => $application->current_address,
                        'father_mobile' => $application->father_mobile,
                        'mother_mobile' => $application->mother_mobile,
                        'emergency_contact' => $placement['emergency_contact'],
                        'admission_date' => $placement['admission_date'],
                        'academic_session_id' => $placement['academic_session_id'],
                        'department_id' => $primaryClass?->department_id ?? $placement['department_id'],
                        'academic_class_id' => $primaryClass?->id ?? $placement['academic_class_id'],
                        'section_id' => $this->primarySectionId($placement),
                        'student_status' => 'Active',
                        'student_type' => $application->student_type,
                        'resident_type' => $placement['resident_type'],
                        'medical_information' => $placement['medical_information'] ?? null,
                        'notes' => $application->notes,
                        // Carried across as recorded on the application. The
                        // admin is never asked to accept on the guardian's
                        // behalf, so these cannot be set during approval.
                        'instructions_accepted' => $application->instructions_accepted,
                        'instructions_accepted_at' => $application->instructions_accepted_at,
                    ]);

                    $this->createEnrollments($application, $student, $placement);
                    $this->linkFather($application, $student);

                    // Not mass assigned: student_id is never accepted from a form.
                    $application->student_id = $student->id;
                    $application->status = 'Approved';
                    $application->save();

                    return $student;
                });
            } catch (UniqueConstraintViolationException $e) {
                if (++$attempts >= 3) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Create a basic father parent record and link it to the student.
     *
     * A new record every time, deliberately. No existing parent is searched
     * for or reused: one father may give a different mobile number for each
     * child, so a number identifies nobody reliably, and a wrong reuse
     * silently attaches a child to the wrong family. Two records for one man
     * is the safer failure, and the admin resolves it from Parent Management
     * by completing the right record (CNIC and the rest) and relinking by
     * hand. Automatic merging and duplicate detection are out of scope.
     *
     * Called inside the approval transaction, so a failure anywhere in the
     * approval rolls this parent and its link back with everything else.
     *
     * The mother is deliberately not handled: the application carries a
     * mother_mobile but no mother name, and a parent record will not be
     * invented from a number alone. Her details stay on the student record
     * as they are today.
     */
    private function linkFather(AdmissionApplication $application, Student $student): void
    {
        $name = trim((string) $application->father_name);
        $mobile = trim((string) $application->father_mobile);

        // A parent record needs at least a name and a mobile to satisfy the
        // rules the Parent forms enforce. Without them the approval simply
        // proceeds without a parent: the admin can link one by hand later.
        if ($name === '' || $mobile === '') {
            return;
        }

        $parent = ParentGuardian::createWithParentId([
            'full_name' => $name,
            'mobile_number' => $mobile,
            // The link being created is Father, so the record is a man's.
            // Nothing else about him is known from the application, and
            // nothing else is invented — CNIC, email, address and occupation
            // are filled in from Parent Management afterwards.
            'gender' => 'Male',
            'parent_status' => 'Active',
        ]);

        $parent->linkStudent($student->id, 'Father', true);
    }

    /**
     * Create the academic enrollments for a newly admitted student.
     *
     * The classes come from the application itself, so a Hifz + School
     * applicant gets one enrollment per track. Called inside the approval
     * transaction, so a failure here rolls the whole approval back.
     *
     * @param  array<string, mixed>  $placement
     */
    private function createEnrollments(
        AdmissionApplication $application,
        Student $student,
        array $placement
    ): void {
        $tracks = [
            'Madrassa' => [
                'class' => $application->madrassaClass,
                'section_id' => $this->sectionForTrack($application, $placement, 'madrassa'),
            ],
            'School' => [
                'class' => $application->schoolClass,
                'section_id' => $this->sectionForTrack($application, $placement, 'school'),
            ],
        ];

        $created = false;

        foreach ($tracks as $track => $details) {
            if (! $details['class']) {
                continue;
            }

            $this->createEnrollment(
                $student,
                $track,
                $details['class']->department_id,
                $details['class']->id,
                $details['section_id'],
                $placement['admission_date'],
                $placement['academic_session_id']
            );

            $created = true;
        }

        if ($created) {
            return;
        }

        // Applications created before the class selection existed, or through
        // the admin form, carry no class of their own. Fall back to the
        // placement chosen during approval so the student still gets one.
        $this->createEnrollment(
            $student,
            $this->trackForStudentType($application->student_type),
            $placement['department_id'],
            $placement['academic_class_id'],
            $placement['section_id'] ?? null,
            $placement['admission_date'],
            $placement['academic_session_id']
        );
    }

    /**
     * Work out which section the admin chose for one track.
     *
     * An application spanning both tracks has a section per track; a
     * single-track application has just the one.
     *
     * @param  array<string, mixed>  $placement
     */
    private function sectionForTrack(
        AdmissionApplication $application,
        array $placement,
        string $side
    ): ?int {
        $spansBothTracks = $application->madrassa_class_id !== null
            && $application->school_class_id !== null;

        if ($spansBothTracks) {
            return $placement["{$side}_section_id"] ?? null;
        }

        return $placement['section_id'] ?? null;
    }

    /**
     * Work out the section stored on the student record itself.
     *
     * The student row holds a single section, so a dual-track applicant is
     * recorded against the madrassa one; the school section lives on that
     * track's enrollment.
     *
     * @param  array<string, mixed>  $placement
     */
    private function primarySectionId(array $placement): ?int
    {
        return $placement['madrassa_section_id'] ?? $placement['section_id'] ?? null;
    }

    /**
     * Record a single active enrollment.
     */
    private function createEnrollment(
        Student $student,
        string $track,
        int $departmentId,
        int $academicClassId,
        ?int $sectionId,
        string $startDate,
        int $academicSessionId
    ): void {
        StudentAcademicEnrollment::create([
            'student_id' => $student->id,
            // The session the admin approved into, the same one written to
            // the student record.
            'academic_session_id' => $academicSessionId,
            'academic_track' => $track,
            'department_id' => $departmentId,
            'academic_class_id' => $academicClassId,
            'section_id' => $sectionId,
            'start_date' => $startDate,
            'status' => 'Active',
        ]);
    }

    /**
     * Work out which track a student type belongs to.
     */
    private function trackForStudentType(?string $studentType): string
    {
        return AdmissionApplication::departmentForSide($studentType, 'madrassa') !== null
            ? 'Madrassa'
            : 'School';
    }

    /**
     * Work out the next student registration number for the current year.
     */
    private function nextRegistrationNumber(): string
    {
        $year = date('Y');
        $prefix = "STD-{$year}-";

        $lastNumber = Student::where('registration_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('registration_number')
            ->value('registration_number');

        $nextNumber = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $application = AdmissionApplication::findOrFail($id);

        return view('admissions.edit', [
            'application' => $application,
            'academicSessions' => AcademicSession::where('status', true)
                ->orderByDesc('start_date')
                ->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAdmissionApplicationRequest $request, string $id)
    {
        $application = AdmissionApplication::findOrFail($id);

        // The status has already been derived from the test result and checked
        // against the workflow rules in UpdateAdmissionApplicationRequest.
        $application->update($request->validated());

        return redirect()
            ->route('admissions.index')
            ->with('success', "Admission application {$application->application_number} updated successfully.");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $application = AdmissionApplication::findOrFail($id);
        $application->delete();

        return redirect()
            ->route('admissions.index')
            ->with('success', 'Admission application deleted successfully.');
    }
}
