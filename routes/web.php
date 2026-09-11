<?php

use App\Http\Controllers\Admin\AcademicClassController;
use App\Http\Controllers\Admin\AcademicController;
use App\Http\Controllers\Admin\AcademicSessionController;
use App\Http\Controllers\Admin\AdmissionApplicationController;
use App\Http\Controllers\Admin\AdmissionFormSettingController;
use App\Http\Controllers\Admin\AdmissionPassedStudentsPdfController;
use App\Http\Controllers\Admin\AdmissionTestSchedulingController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AttendanceReportController;
use App\Http\Controllers\Admin\AttendanceSessionSummaryController;
use App\Http\Controllers\Admin\ComputerCourseController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DisciplineRecordController;
use App\Http\Controllers\Admin\MadrassaDailyRecordController;
use App\Http\Controllers\Admin\MadrassaDailyRecordReportController;
use App\Http\Controllers\Admin\ParentController;
use App\Http\Controllers\Admin\ParentStudentController;
use App\Http\Controllers\Admin\PrayerAttendanceController;
use App\Http\Controllers\Admin\PrayerAttendanceReportController;
use App\Http\Controllers\Admin\PrayerAttendanceSessionSummaryController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShortResultReportPdfController;
use App\Http\Controllers\Admin\StudentAcademicEnrollmentController;
use App\Http\Controllers\Admin\StudentAttendanceHistoryController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentDailyRecordHistoryController;
use App\Http\Controllers\Admin\StudentDisciplineHistoryController;
use App\Http\Controllers\Admin\StudentMadrassaProgressController;
use App\Http\Controllers\Admin\StudentPerformanceReportPdfController;
use App\Http\Controllers\Admin\StudentPrayerAttendanceHistoryController;
use App\Http\Controllers\Admin\StudentPromotionController;
use App\Http\Controllers\Admin\StudentResultController;
use App\Http\Controllers\Admin\StudentResultHistoryController;
use App\Http\Controllers\Admin\StudentResultReportController;
use App\Http\Controllers\Admin\StudentTermsSettingController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicAdmissionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

/*
 * Public online admission form (no login required).
 *
 * Declared before the authenticated admissions resource below: otherwise
 * "apply" would be captured by the admissions/{admission} show route and
 * bounced to the login page.
 */
Route::controller(PublicAdmissionController::class)->group(function () {
    Route::get('admissions/apply', 'create')->name('public.admissions.apply');
    Route::post('admissions/apply', 'store')->name('public.admissions.store');
    Route::get('admissions/apply/confirmation', 'confirmation')->name('public.admissions.confirmation');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // User Management
    Route::resource('users', UserController::class);

    // Institution settings. One global record, so there is no index, no
    // create, no destroy and - deliberately - no id in either URL: there is
    // no parameter a browser could change to reach a second record, and no
    // route by which a second one could be made.
    Route::get('settings', [SettingController::class, 'edit'])
        ->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])
        ->name('settings.update');

    // When the public admission form is reachable. A second page in the same
    // module, writing the same single row, and carrying no id either.
    Route::get('settings/admission-form', [AdmissionFormSettingController::class, 'edit'])
        ->name('settings.admission-form.edit');
    Route::put('settings/admission-form', [AdmissionFormSettingController::class, 'update'])
        ->name('settings.admission-form.update');

    // The instructions each department's guardians agree to. A third
    // settings page beside the two above, behind the same permissions.
    Route::get('settings/student-terms', [StudentTermsSettingController::class, 'edit'])
        ->name('settings.student-terms.edit');
    Route::put('settings/student-terms', [StudentTermsSettingController::class, 'update'])
        ->name('settings.student-terms.update');

    // Academic Sessions
    Route::resource('academic-sessions', AcademicSessionController::class);

    // Departments
    Route::resource('departments', DepartmentController::class);

    // Classes
    Route::resource('classes', AcademicClassController::class);

    // Sections

    // The Computer department's course structure: one course, six semesters.
    // Read and edit only - the structure is seeded with the department, and
    // what an admin configures is its dates and curriculum. Declared before
    // the semester routes so "semesters" is never read as a course.
    Route::get('computer-course', [ComputerCourseController::class, 'index'])
        ->name('computer-course.index');
    Route::get('computer-course/semesters/{semester}/edit', [ComputerCourseController::class, 'editSemester'])
        ->name('computer-course.semesters.edit');
    Route::put('computer-course/semesters/{semester}', [ComputerCourseController::class, 'updateSemester'])
        ->name('computer-course.semesters.update');
    Route::get('computer-course/{computerCourse}/edit', [ComputerCourseController::class, 'edit'])
        ->name('computer-course.edit');
    Route::put('computer-course/{computerCourse}', [ComputerCourseController::class, 'update'])
        ->name('computer-course.update');
    Route::resource('sections', SectionController::class);

    // Students
    Route::resource('students', StudentController::class);

    // Academic Management: the central view over the enrollment records.
    Route::get('academics', [AcademicController::class, 'index'])
        ->name('academics.index');
    // A group is a filtered view of those records, not a stored entity.
    Route::get('academics/groups', [AcademicController::class, 'group'])
        ->name('academics.groups');
    Route::get('academics/enrollments/{enrollment}', [AcademicController::class, 'showEnrollment'])
        ->name('academics.enrollments.show');

    // Monthly attendance entry. The sheet is a filtered view of the
    // enrollments, so there is one page: the filters draw a month of them
    // and the save writes it back.
    Route::get('attendance', [AttendanceController::class, 'index'])
        ->name('attendance.index');
    Route::post('attendance', [AttendanceController::class, 'store'])
        ->name('attendance.store');

    // Monthly reports over what the sheet has recorded. Read only: it
    // counts rows, it never writes one.
    // The print and export routes are declared first so neither is captured
    // by anything the reports page adds later.
    Route::get('attendance/reports/print', [AttendanceReportController::class, 'printReport'])
        ->name('attendance.reports.print');
    Route::get('attendance/reports/export', [AttendanceReportController::class, 'export'])
        ->name('attendance.reports.export');
    Route::get('attendance/reports', [AttendanceReportController::class, 'index'])
        ->name('attendance.reports');

    // A whole session at a glance, for one track. Read only, and the print
    // and export routes are declared first so neither is captured by the
    // summary route.
    Route::get('attendance/session-summary/print', [AttendanceSessionSummaryController::class, 'printSummary'])
        ->name('attendance.session-summary.print');
    Route::get('attendance/session-summary/export', [AttendanceSessionSummaryController::class, 'export'])
        ->name('attendance.session-summary.export');
    Route::get('attendance/session-summary', [AttendanceSessionSummaryController::class, 'index'])
        ->name('attendance.session-summary');

    // The month as a printable register. Read only, like everything else
    // here: printing a sheet never writes a mark.
    Route::get('attendance/print', [AttendanceController::class, 'printSheet'])
        ->name('attendance.print');

    // Prayer attendance: the five daily prayers, Madrassa only. A separate
    // register from the academic attendance above, in its own table, and
    // neither one reads the other's rows. Like the academic sheet, the
    // filters draw a month of enrollments and the save writes it back.
    Route::get('prayer-attendance', [PrayerAttendanceController::class, 'index'])
        ->name('prayer-attendance.index');
    Route::post('prayer-attendance', [PrayerAttendanceController::class, 'store'])
        ->name('prayer-attendance.store');

    // Monthly totals over what the sheet has recorded. Read only: it counts
    // rows, it never writes one, and a prayer nobody entered is reported as
    // unrecorded rather than as an absence.
    Route::get('prayer-attendance/reports', [PrayerAttendanceReportController::class, 'index'])
        ->name('prayer-attendance.reports');

    // A whole academic session at a glance. Read only, and expected prayers
    // are counted from each student's own enrollment period rather than
    // from the calendar.
    Route::get('prayer-attendance/session-summary', [PrayerAttendanceSessionSummaryController::class, 'index'])
        ->name('prayer-attendance.session-summary');

    // One student's prayer history. Read only: corrections are made on the
    // monthly sheet above, which is the module's only writer.
    Route::get('students/{student}/prayer-attendance', [StudentPrayerAttendanceHistoryController::class, 'index'])
        ->name('students.prayer-attendance');

    // One student's recorded attendance. A viewing page: corrections are
    // made on the monthly sheet above, which is the only writer.
    // Declared before the history route so "print" is not read as part of
    // it, and kept inside the same authenticated group.
    Route::get('students/{student}/attendance/print', [StudentAttendanceHistoryController::class, 'printHistory'])
        ->name('students.attendance.print');
    Route::get('students/{student}/attendance', [StudentAttendanceHistoryController::class, 'index'])
        ->name('students.attendance');

    // Hifz & Quran: the madrassa daily academic record. One page holds the
    // whole workflow - the roster for a chosen day and class, and the
    // records already on file - so "create" is declared before the record
    // routes or it would be read as a record id.
    //
    // No destroy route. A mistake is corrected on the record it was made
    // on; a day's academic history is not something an admin deletes.
    Route::get('hifz', [MadrassaDailyRecordController::class, 'index'])
        ->name('hifz.index');
    // Reports over what the roster has recorded. Read only: it counts
    // recorded days and repeats what was written, it never writes a record
    // and it never does arithmetic on a Quran quantity.
    // Declared before the record routes or "reports" would be read as a
    // record id.
    Route::get('hifz/reports', [MadrassaDailyRecordReportController::class, 'index'])
        ->name('hifz.reports');
    Route::get('hifz/create', [MadrassaDailyRecordController::class, 'create'])
        ->name('hifz.create');
    Route::post('hifz', [MadrassaDailyRecordController::class, 'store'])
        ->name('hifz.store');
    Route::get('hifz/{record}/edit', [MadrassaDailyRecordController::class, 'edit'])
        ->name('hifz.edit');
    Route::get('hifz/{record}', [MadrassaDailyRecordController::class, 'show'])
        ->name('hifz.show');
    Route::put('hifz/{record}', [MadrassaDailyRecordController::class, 'update'])
        ->name('hifz.update');

    // One student's daily records. A viewing page: entry and corrections
    // happen on the roster above, which is the module's only writer.
    // "progress" is declared first so it is not read as part of the
    // history route.
    Route::get('students/{student}/hifz/progress', [StudentMadrassaProgressController::class, 'show'])
        ->name('students.hifz.progress');
    Route::get('students/{student}/hifz', [StudentDailyRecordHistoryController::class, 'index'])
        ->name('students.hifz');

    // Madrassa results. One page holds the whole workflow - the madrassa
    // students matching the filters, each with that term's Grand Test
    // result or "Not Entered" - so "create" is declared before the result
    // routes or it would be read as a result id.
    //
    // No destroy route, following the Hifz module. A mistake is corrected
    // on the result it was made on; a student's marks are not something an
    // admin deletes.
    Route::get('results', [StudentResultController::class, 'index'])
        ->name('results.index');
    Route::get('results/create', [StudentResultController::class, 'create'])
        ->name('results.create');
    Route::post('results', [StudentResultController::class, 'store'])
        ->name('results.store');
    // The result report. Read only: it repeats what the Results page has
    // recorded and names who has not been marked yet, and it never writes a
    // result.
    // Declared before the result routes or "reports" would be read as a
    // result id.
    Route::get('results/reports', [StudentResultReportController::class, 'index'])
        ->name('results.reports');
    // The short, result-focused report as a PDF the browser opens in a
    // tab. Read only, Madrassa-only, and declared before the result routes
    // or "reports" would be read as a result id.
    //
    // This is the group form: every student the reports page filters to.
    // The long detailed track record is a different document on a route of
    // its own and is not reachable from here.
    Route::get('results/reports/pdf', [ShortResultReportPdfController::class, 'group'])
        ->name('results.reports.pdf');
    Route::get('results/{result}/edit', [StudentResultController::class, 'edit'])
        ->name('results.edit');
    Route::get('results/{result}', [StudentResultController::class, 'show'])
        ->name('results.show');
    Route::put('results/{result}', [StudentResultController::class, 'update'])
        ->name('results.update');

    // One student's result history. Read only: results are recorded and
    // corrected on the Results page above, which is the module's only
    // writer. The student is the route parameter and the madrassa
    // enrollments are gathered from that student alone, so no query
    // parameter can reach another student's results or the school side of
    // this one's.
    // One student's short result report - the PDF action on a result row.
    // The same document the group report prints, for one student.
    Route::get('students/{student}/results/short-pdf', [ShortResultReportPdfController::class, 'student'])
        ->name('students.results.short-pdf');

    // One student's detailed Madrassa track record, as a PDF. A separate,
    // much longer document, kept on its own route and reached from its own
    // action. Declared before the history route so "pdf" is not read as
    // part of it. The student is the route binding and the Madrassa
    // placements are derived from that student alone; a student with none
    // gets a 404 rather than an empty document.
    Route::get('students/{student}/results/pdf', [StudentPerformanceReportPdfController::class, 'show'])
        ->name('students.results.pdf');

    Route::get('students/{student}/results', [StudentResultHistoryController::class, 'index'])
        ->name('students.results');

    // Discipline. One incident recorded against one student: what
    // happened, how serious it was, and what the office did about it.
    // "create" is declared before the record routes or it would be read as
    // a record id.
    //
    // No destroy route, following Hifz and Results. A mistake is corrected
    // on the record it was made on; a student's discipline history is not
    // something an admin deletes.
    Route::get('discipline', [DisciplineRecordController::class, 'index'])
        ->name('discipline.index');
    Route::get('discipline/create', [DisciplineRecordController::class, 'create'])
        ->name('discipline.create');
    Route::post('discipline', [DisciplineRecordController::class, 'store'])
        ->name('discipline.store');
    Route::get('discipline/{discipline}/edit', [DisciplineRecordController::class, 'edit'])
        ->name('discipline.edit');
    Route::get('discipline/{discipline}', [DisciplineRecordController::class, 'show'])
        ->name('discipline.show');
    Route::put('discipline/{discipline}', [DisciplineRecordController::class, 'update'])
        ->name('discipline.update');

    // One student's discipline history. Read only: records are written and
    // corrected in the Discipline module above, which is the only writer.
    // The student is the route parameter and every record is drawn with
    // that student's id as the leading condition, so no query parameter can
    // reach another student's records.
    Route::get('students/{student}/discipline', [StudentDisciplineHistoryController::class, 'index'])
        ->name('students.discipline');

    // Student promotion. Always explicit: the form is opened, reviewed and
    // submitted by an admin.
    Route::get('students/{student}/promote', [StudentPromotionController::class, 'create'])
        ->name('students.promote');
    Route::post('students/{student}/promote', [StudentPromotionController::class, 'store'])
        ->name('students.promote.store');

    // Academic enrollments / academic history, managed from the student
    // profile. No index route: the history is a section on that page.
    Route::post('students/{student}/enrollments', [StudentAcademicEnrollmentController::class, 'store'])
        ->name('students.enrollments.store');
    Route::get('students/{student}/enrollments/{enrollment}/edit', [StudentAcademicEnrollmentController::class, 'edit'])
        ->name('students.enrollments.edit');
    Route::put('students/{student}/enrollments/{enrollment}', [StudentAcademicEnrollmentController::class, 'update'])
        ->name('students.enrollments.update');
    Route::delete('students/{student}/enrollments/{enrollment}', [StudentAcademicEnrollmentController::class, 'destroy'])
        ->name('students.enrollments.destroy');

    // Parent <-> student links, reachable from either profile.
    Route::post('students/{student}/parents', [ParentStudentController::class, 'storeFromStudent'])
        ->name('students.parents.store');
    Route::delete('students/{student}/parents/{parent}', [ParentStudentController::class, 'destroyFromStudent'])
        ->name('students.parents.destroy');

    // Teachers
    Route::resource('teachers', TeacherController::class);

    // Parents
    Route::resource('parents', ParentController::class);

    Route::post('parents/{parent}/students', [ParentStudentController::class, 'storeFromParent'])
        ->name('parents.students.store');
    Route::delete('parents/{parent}/students/{student}', [ParentStudentController::class, 'destroyFromParent'])
        ->name('parents.students.destroy');

    // Admission Applications
    // Declared before the resource so "test-scheduling" is not captured by
    // the admissions/{admission} show route.
    Route::get('admissions/test-scheduling', [AdmissionTestSchedulingController::class, 'create'])
        ->name('admissions.test-scheduling');
    Route::post('admissions/test-scheduling', [AdmissionTestSchedulingController::class, 'store'])
        ->name('admissions.test-scheduling.store');

    // The notice board sheet of who passed the admission test. Read only,
    // and declared before the resource for the same reason: "passed-students"
    // must not be read as an application id.
    Route::get('admissions/passed-students/pdf', [AdmissionPassedStudentsPdfController::class, 'index'])
        ->name('admissions.passed-students.pdf');

    Route::post('admissions/{admission}/approve', [AdmissionApplicationController::class, 'approve'])
        ->name('admissions.approve');
    Route::resource('admissions', AdmissionApplicationController::class);
});

require __DIR__.'/auth.php';
