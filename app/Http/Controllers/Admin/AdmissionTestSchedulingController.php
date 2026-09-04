<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScheduleAdmissionTestsRequest;
use App\Models\AdmissionApplication;

/**
 * Bulk admission test scheduling by student type.
 *
 * Kept separate from AdmissionApplicationController so the per-application
 * scheduling on the edit page is unaffected.
 */
class AdmissionTestSchedulingController extends Controller
{
    /**
     * Show the bulk test scheduling page.
     */
    public function create()
    {
        // Set by store() on the previous request: the applications just
        // scheduled, so the admin can message those parents on WhatsApp.
        $scheduledIds = session('scheduled_application_ids', []);

        return view('admissions.test-scheduling', [
            'eligibleCounts' => $this->eligibleCounts(),
            'justScheduled' => $scheduledIds
                ? AdmissionApplication::whereIn('id', $scheduledIds)->orderBy('application_number')->get()
                : collect(),
        ]);
    }

    /**
     * Schedule the admission test for every eligible application of a type.
     */
    public function store(ScheduleAdmissionTestsRequest $request)
    {
        $data = $request->validated();

        // Only Pending and Under Review are touched, so applications that are
        // already scheduled, completed, passed, failed, approved or rejected
        // are left exactly as they are.
        //
        // The ids are captured first so the update targets exactly the rows
        // that were eligible, and the follow-up WhatsApp list can show the
        // applications that really were scheduled.
        $ids = AdmissionApplication::query()
            ->where('student_type', $data['student_type'])
            ->whereIn('status', AdmissionApplication::BULK_SCHEDULABLE_STATUSES)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return redirect()
                ->route('admissions.test-scheduling')
                ->with('warning', "No applications were scheduled: there are no Pending or Under Review {$data['student_type']} applications.");
        }

        $scheduled = AdmissionApplication::whereIn('id', $ids)->update([
            'test_date' => $data['test_date'],
            'test_time' => $data['test_time'],
            'status' => 'Test Scheduled',
        ]);

        return redirect()
            ->route('admissions.test-scheduling')
            ->with('scheduled_application_ids', $ids)
            ->with('success', $scheduled === 1
                ? "1 {$data['student_type']} application was scheduled for the admission test."
                : "{$scheduled} {$data['student_type']} applications were scheduled for the admission test.");
    }

    /**
     * Count the applications each student type could schedule right now.
     *
     * @return array<string, int>
     */
    private function eligibleCounts(): array
    {
        $counts = AdmissionApplication::query()
            ->whereIn('status', AdmissionApplication::BULK_SCHEDULABLE_STATUSES)
            ->selectRaw('student_type, COUNT(*) as total')
            ->groupBy('student_type')
            ->pluck('total', 'student_type');

        return collect(AdmissionApplication::STUDENT_TYPES)
            ->mapWithKeys(fn (string $type) => [$type => (int) $counts->get($type, 0)])
            ->all();
    }
}
