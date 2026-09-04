<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicAdmissionApplicationRequest;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Department;
use App\Models\Setting;
use Illuminate\Http\Response;

/**
 * Handles the public, unauthenticated online admission form.
 *
 * Kept entirely separate from Admin\AdmissionApplicationController: this
 * controller can only create a Pending application. It never reads, lists or
 * updates existing applications, and never touches student records.
 */
class PublicAdmissionController extends Controller
{
    /**
     * Show the public admission form.
     */
    public function create()
    {
        if ($closed = $this->closedResponse()) {
            return $closed;
        }

        return view('public.admissions.apply', [
            'classesByDepartment' => $this->classesByDepartment(),
            // Shown on the form so an applicant can see which year they are
            // applying for. It is displayed, never posted: the session saved
            // against the application is resolved again server side when the
            // form is submitted, so what the browser sends cannot matter.
            'academicSession' => AcademicSession::current(),
        ]);
    }

    /**
     * Get the active classes of every department, keyed by department name.
     *
     * Read straight from the existing Master Data, so the form always offers
     * whatever the school has actually set up.
     *
     * @return array<string, array<int, array{id: int, name: string}>>
     */
    private function classesByDepartment(): array
    {
        return Department::where('status', true)
            ->with(['academicClasses' => function ($query) {
                $query->where('status', true)->orderBy('id');
            }])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Department $department) => [
                $department->name => $department->academicClasses
                    ->map(fn ($class) => ['id' => $class->id, 'name' => $class->name])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * Store a publicly submitted admission application.
     */
    public function store(PublicAdmissionApplicationRequest $request)
    {
        // The same check the form itself is behind, and the reason it is a
        // method rather than a copied condition. Protecting only the page
        // would leave the window trivially bypassed: open the form while it
        // is available, leave the tab sitting there, and post it a week
        // after admissions closed. This runs before anything is stored - no
        // photo is written and no application is created - so a submission
        // outside the window leaves nothing behind.
        if ($closed = $this->closedResponse()) {
            return $closed;
        }

        $data = $request->validated();

        // The academic session is not read from $data and cannot be: the
        // request declares no rule for it, so validated() has already dropped
        // anything posted under that name. createWithApplicationNumber()
        // resolves the current session itself and stamps it on the record.
        // A request carrying academic_session_id for last year, or for a year
        // that has not started, is saved into the current session like any
        // other.

        // Status is fixed server side; the public form cannot choose it.
        $data['status'] = 'Pending';

        // validated() hands back the UploadedFile itself, which must never be
        // persisted. Drop it, then store only the resulting path.
        unset($data['photo']);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('admission-photos', 'public');
        }

        // Validation has confirmed the guardian ticked the box, so the server
        // records the agreement itself. The posted value is discarded and the
        // timestamp is never accepted from the request.
        $data['instructions_accepted'] = true;
        $data['instructions_accepted_at'] = now();

        $application = AdmissionApplication::createWithApplicationNumber($data);

        // The confirmation details travel in the session rather than the URL so
        // an application number cannot be swapped to view someone else's data.
        return redirect()
            ->route('public.admissions.confirmation')
            ->with('admission_confirmation', [
                'application_number' => $application->application_number,
                'student_name' => $application->student_name,
                'status' => $application->status,
            ]);
    }

    /**
     * Refuse the request when the public admission form may not be used.
     *
     * Null when admissions are open, which is what lets both actions read as
     * "carry on unless this says otherwise". One method for the page and the
     * submission, so the two enforce the same rule by construction and a
     * change to one cannot leave the other behind.
     *
     * The answer comes from Setting::admissionFormState() alone: the stored
     * schedule measured against the server's clock. No part of the request
     * is consulted, so no hidden field, query parameter, edited script or
     * wrong browser clock can change it.
     *
     * 403 rather than a redirect. The visitor asked for something they are
     * not allowed to have right now, and the closed page is the explanation
     * rather than an error - which is why the status carries a rendered
     * view instead of an abort page.
     */
    private function closedResponse(): ?Response
    {
        $institution = Setting::current();

        if ($institution->admissionFormIsOpen()) {
            return null;
        }

        return response()->view('public.admissions.closed', [
            'state' => $institution->admissionFormState(),
            'institution' => $institution,
        ], 403);
    }

    /**
     * Show the confirmation for the application just submitted.
     */
    public function confirmation()
    {
        $confirmation = session('admission_confirmation');

        // Nothing to confirm (direct visit or refresh): send them to the form.
        if (! $confirmation) {
            return redirect()->route('public.admissions.apply');
        }

        return view('public.admissions.confirmation', compact('confirmation'));
    }
}
