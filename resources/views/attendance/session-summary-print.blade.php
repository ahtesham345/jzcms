@php
    use App\Models\StudentAttendance;

    $percentageLabel = fn (?float $percentage) => $percentage === null ? 'N/A' : number_format($percentage, 2).'%';

    $groupLabel = collect([
        $group['track'],
        $group['department']?->name,
        $group['academicClass']?->name,
        $group['section'] ? 'Section ' . $group['section']->name : null,
    ])->filter()->implode(' - ');
@endphp

<x-layout.print
    title="Session Attendance Summary"
    :heading="'Session Attendance Summary - ' . $session->name"
    :back="route('attendance.session-summary', array_filter($filters, fn ($value) => $value !== null && $value !== ''))"
>
    <!-- What this summary covers -->
    <table class="w-full text-xs mb-3">
        <tbody>
            <tr>
                <td class="py-0.5"><span class="font-semibold">Academic Session:</span> {{ $session->name }}</td>
                <td class="py-0.5"><span class="font-semibold">Track:</span> {{ $filters['academic_track'] }}</td>
                <td class="py-0.5"><span class="font-semibold">Registers a day:</span> {{ implode(', ', $periods) }}</td>
            </tr>
            <tr>
                <td class="py-0.5"><span class="font-semibold">Department:</span> {{ $group['department']?->name ?? 'All departments' }}</td>
                <td class="py-0.5"><span class="font-semibold">Class:</span> {{ $group['academicClass']?->name ?? 'All classes' }}</td>
                <td class="py-0.5"><span class="font-semibold">Section:</span> {{ $group['section']?->name ?? 'All sections' }}</td>
            </tr>
            <tr>
                <td class="py-0.5" colspan="2">
                    <span class="font-semibold">Session dates:</span>
                    {{ $sessionStart->format('d M, Y') }} to {{ $sessionEnd->format('d M, Y') }}
                </td>
                <td class="py-0.5"><span class="font-semibold">Teaching days:</span> {{ $totals['session_teaching_days'] }}</td>
            </tr>
            @if($filters['search'])
                <tr>
                    <td class="py-0.5" colspan="3"><span class="font-semibold">Search:</span> {{ $filters['search'] }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- Summary -->
    <table class="w-full border-collapse text-xs mb-3">
        <thead>
            <tr>
                <th class="border border-gray-400 px-2 py-1 text-left">Students</th>
                <th class="border border-gray-400 px-2 py-1 text-left">Opportunities</th>
                <th class="border border-gray-400 px-2 py-1 text-left">Recorded</th>
                <th class="border border-gray-400 px-2 py-1 text-left">Unrecorded</th>
                <th class="border border-gray-400 px-2 py-1 text-left">Present</th>
                <th class="border border-gray-400 px-2 py-1 text-left">Absent</th>
                <th class="border border-gray-400 px-2 py-1 text-left">Overall Attendance</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="border border-gray-400 px-2 py-1 font-semibold">{{ $totals['students'] }}</td>
                <td class="border border-gray-400 px-2 py-1 font-semibold">{{ $totals['opportunities'] }}</td>
                <td class="border border-gray-400 px-2 py-1 font-semibold">{{ $totals['recorded'] }}</td>
                <td class="border border-gray-400 px-2 py-1 font-semibold">{{ $totals['unrecorded'] }}</td>
                <td class="border border-gray-400 px-2 py-1 font-semibold">{{ $totals['present'] }}</td>
                <td class="border border-gray-400 px-2 py-1 font-semibold">{{ $totals['absent'] }}</td>
                <td class="border border-gray-400 px-2 py-1 font-semibold">{{ $percentageLabel($totals['percentage']) }}</td>
            </tr>
        </tbody>
    </table>

    <p class="text-[10px] text-gray-600 mb-3">
        Attendance Opportunities is what the registers could hold across every student in this group; Recorded is what
        has been transcribed. Attendance percentage is Present divided by Recorded, so untranscribed attendance is not
        counted against a student. Saturdays and Sundays are never an opportunity. N/A means nothing has been recorded.
    </p>

    <!-- Students -->
    <h3 class="text-sm font-semibold text-gray-900 mb-1">Student Summary</h3>

    @if($students->isEmpty())
        <p class="text-sm text-gray-700 mb-4">No students found for the selected filters.</p>
    @else
        <table class="w-full border-collapse text-xs mb-6">
            <thead>
                <tr>
                    <th class="border border-gray-400 px-2 py-1 text-left">#</th>
                    <th class="border border-gray-400 px-2 py-1 text-left">Student</th>
                    <th class="border border-gray-400 px-2 py-1 text-left">Reg. No.</th>
                    <th class="border border-gray-400 px-2 py-1 text-left">Roll</th>
                    <th class="border border-gray-400 px-2 py-1 text-left">Department</th>
                    <th class="border border-gray-400 px-2 py-1 text-left">Class</th>
                    <th class="border border-gray-400 px-2 py-1 text-left">Section</th>
                    <th class="border border-gray-400 px-2 py-1 text-left">Track</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Days</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Opportunities</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Recorded</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Unrecorded</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Present</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Absent</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Attendance %</th>
                </tr>
            </thead>
            <tbody>
                @foreach($students as $student)
                    <tr>
                        <td class="border border-gray-400 px-2 py-1">{{ $loop->iteration }}</td>
                        <td class="border border-gray-400 px-2 py-1 whitespace-nowrap">{{ $student['full_name'] }}</td>
                        <td class="border border-gray-400 px-2 py-1 whitespace-nowrap">{{ $student['registration_number'] }}</td>
                        <td class="border border-gray-400 px-2 py-1">{{ $student['roll_number'] ?? '—' }}</td>
                        {{-- From the enrollment, so a promoted student still
                             reports the placement they sat the session in. --}}
                        <td class="border border-gray-400 px-2 py-1 whitespace-nowrap">{{ $student['department'] }}</td>
                        <td class="border border-gray-400 px-2 py-1 whitespace-nowrap">{{ $student['class'] }}</td>
                        <td class="border border-gray-400 px-2 py-1 whitespace-nowrap">{{ $student['section'] }}</td>
                        <td class="border border-gray-400 px-2 py-1">{{ $student['academic_track'] }}</td>
                        <td class="border border-gray-400 px-2 py-1 text-right">{{ $student['teaching_days'] }}</td>
                        <td class="border border-gray-400 px-2 py-1 text-right">{{ $student['opportunities'] }}</td>
                        <td class="border border-gray-400 px-2 py-1 text-right">{{ $student['recorded'] }}</td>
                        <td class="border border-gray-400 px-2 py-1 text-right">{{ $student['unrecorded'] }}</td>
                        <td class="border border-gray-400 px-2 py-1 text-right">{{ $student['present'] }}</td>
                        <td class="border border-gray-400 px-2 py-1 text-right">{{ $student['absent'] }}</td>
                        <td class="border border-gray-400 px-2 py-1 text-right font-semibold">
                            {{ StudentAttendance::formatPercentage($student['present'], $student['absent']) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- Monthly breakdown -->
    <h3 class="text-sm font-semibold text-gray-900 mb-1">Monthly Breakdown</h3>

    <table class="w-full border-collapse text-xs">
        <thead>
            <tr>
                <th class="border border-gray-400 px-2 py-1 text-left">Month</th>
                <th class="border border-gray-400 px-2 py-1 text-right">Teaching Days</th>
                <th class="border border-gray-400 px-2 py-1 text-right">Opportunities</th>
                <th class="border border-gray-400 px-2 py-1 text-right">Recorded</th>
                <th class="border border-gray-400 px-2 py-1 text-right">Present</th>
                <th class="border border-gray-400 px-2 py-1 text-right">Absent</th>
                <th class="border border-gray-400 px-2 py-1 text-right">Attendance %</th>
            </tr>
        </thead>
        <tbody>
            @foreach($months as $month)
                <tr>
                    <td class="border border-gray-400 px-2 py-1">{{ $month['label'] }}</td>
                    <td class="border border-gray-400 px-2 py-1 text-right">{{ $month['session_teaching_days'] }}</td>
                    <td class="border border-gray-400 px-2 py-1 text-right">{{ $month['opportunities'] }}</td>
                    <td class="border border-gray-400 px-2 py-1 text-right">{{ $month['recorded'] }}</td>
                    <td class="border border-gray-400 px-2 py-1 text-right">{{ $month['present'] }}</td>
                    <td class="border border-gray-400 px-2 py-1 text-right">{{ $month['absent'] }}</td>
                    <td class="border border-gray-400 px-2 py-1 text-right font-semibold">{{ $percentageLabel($month['percentage']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-8 flex justify-between text-xs">
        <div>
            <div class="border-t border-gray-500 w-48 pt-1">Prepared By</div>
        </div>
        <div>
            <div class="border-t border-gray-500 w-48 pt-1">Principal</div>
        </div>
    </div>
</x-layout.print>
