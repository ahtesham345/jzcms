<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateStudentTermsSettingRequest;
use App\Models\DepartmentTerm;
use App\Support\StudentTerms;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * The instructions each department's guardians agree to.
 *
 * A third page in the existing Settings module, behind the same two
 * permissions and with the same two actions as the admission form schedule
 * beside it. It is its own page rather than a section of the general
 * settings form because these are several blocks of Urdu prose the Imam
 * edits as the year goes on, and they have no business inside a form about
 * the institution's name and address.
 *
 * One block per real department. The combined student types have no block:
 * Hifz + School is two placements, and a guardian is shown the two
 * departments' instructions together. Nothing here creates or needs a
 * combined department.
 *
 * Nothing here decides which instructions a student sees either. That is
 * StudentTerms', which the three pages that display them all read.
 */
class StudentTermsSettingController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show the instructions for every department.
     */
    public function edit()
    {
        $this->authorize('settings.view');

        return view('settings.student-terms', [
            'departments' => StudentTerms::editableByDepartment(),
            'heading' => StudentTerms::heading(),
            'agreement' => StudentTerms::agreement(),
        ]);
    }

    /**
     * Save the instructions.
     *
     * One row per department, written whether or not one existed before, so
     * the first save of a department that has been showing the defaults
     * records them as its own. Only the departments the form offered are
     * touched - the request has already refused any other id - so a
     * department created by mistake cannot be given instructions through a
     * hand-crafted submission.
     */
    public function update(UpdateStudentTermsSettingRequest $request)
    {
        $this->authorize('settings.update');

        foreach ($request->termsByDepartment() as $departmentId => $items) {
            DepartmentTerm::updateOrCreate(
                ['department_id' => $departmentId],
                ['items' => $items, 'status' => true]
            );
        }

        return redirect()
            ->route('settings.student-terms.edit')
            ->with('success', 'Student instructions updated successfully.');
    }
}
