<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdmissionFormSettingRequest;
use App\Models\Setting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * When the public admission form is reachable.
 *
 * A second page in the existing Settings module rather than a second
 * settings system: it reads and writes the same singleton row
 * Setting::current() resolves, behind the same two permissions, with the
 * same two actions and the same absence of an id in either URL. What it
 * does not do is share the general settings form, because a schedule the
 * institution changes every admission season has no business being buried
 * in a page about the institution's name and address.
 *
 * Nothing here decides whether admissions are open. That question is
 * answered in one place, Setting::admissionFormState(), which the public
 * form and the public submission both go through.
 */
class AdmissionFormSettingController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show the admission form schedule.
     *
     * The same view whether a window has been saved or not. current() hands
     * back the stored row or an unsaved instance carrying the defaults - and
     * the default is off with no window - so an institution that has never
     * configured admissions sees a switched-off form rather than a blank one
     * it has to interpret.
     */
    public function edit()
    {
        $this->authorize('settings.view');

        $settings = Setting::current();

        return view('settings.admission-form', [
            'settings' => $settings,
            'state' => $settings->admissionFormState(),
        ]);
    }

    /**
     * Save the admission form schedule.
     *
     * The columns come from the request's scheduleData() rather than from
     * validated(): four inputs are posted and three columns are written, and
     * turning one into the other is the request's business.
     */
    public function update(UpdateAdmissionFormSettingRequest $request)
    {
        $this->authorize('settings.update');

        $settings = Setting::current();

        $settings->fill($request->scheduleData());

        $settings->save();

        return redirect()
            ->route('settings.admission-form.edit')
            ->with('success', 'Admission form settings saved successfully.');
    }
}
