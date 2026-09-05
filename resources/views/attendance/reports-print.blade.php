@php
    use App\Models\StudentAttendance;

    $percentageLabel = fn (?float $percentage) => $percentage === null ? 'N/A' : number_format($percentage, 2).'%';

    $periodLabel = $filters['attendance_period'] === $allPeriodsValue
        ? 'All periods'
        : $filters['attendance_period'];
@endphp

<x-layout.print
    title="Attendance Report"
    :heading="'Monthly Attendance Report - ' . $monthLabel"
    :back="route('attendance.reports', array_filter($filters, fn ($value) => $value !== null && $value !== ''))"
>
    <!-- What this report covers -->
    <table class="w-full text-xs mb-3">
        <tbody>
            <tr>
                <td class="py-0.5"><span class="font-semibold">Track:</span> {{ $filters['academic_track'] }}</td>
                <td class="py-0.5"><span class="font-semibold">Month:</span> {{ $monthLabel }}</td>
                <td class="py-0.5"><span class="font-semibold">Period:</span> {{ $periodLabel }}</td>
            </tr>
            @if($filters['search'])
                <tr>
                    <td class="py-0.5" colspan="3"><span class="font-semibold">Search:</span> {{ $filters['search'] }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- Summary -->
    <table class="w-full border-collapse text-xs mb-4">
        <thead>
            <tr>
                <th class="border border-gray-400 px-2 py-1 text-left">Total Students</th>
                <th class="border border-gray-400 px-2 py-1 text-left">Total Recorded</th>
                <th class="border border-gray-400 px-2 py-1 text-left">Present</th>
                <th class="border border-gray-400 px-2 py-1 text-left">Absent</th>
                <th class="border border-gray-400 px-2 py-1 text-left">Overall Attendance</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="border border-gray-400 px-2 py-1 font-semibold">{{ $summary['students'] }}</td>
                <td class="border border-gray-400 px-2 py-1 font-semibold">{{ $summary['recorded'] }}</td>
                <td class="border border-gray-400 px-2 py-1 font-semibold">{{ $summary['present'] }}</td>
                <td class="border border-gray-400 px-2 py-1 font-semibold">{{ $summary['absent'] }}</td>
                <td class="border border-gray-400 px-2 py-1 font-semibold">{{ $percentageLabel($summary['percentage']) }}</td>
            </tr>
        </tbody>
    </table>

    <p class="text-[10px] text-gray-600 mb-3">
        Attendance percentage is Present divided by Recorded. Sundays, and days that have not been entered yet,
        are not in the denominator. N/A means nothing has been recorded.
    </p>

    <!-- Student summary -->
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
                    <th class="border border-gray-400 px-2 py-1 text-left">Class</th>
                    <th class="border border-gray-400 px-2 py-1 text-left">Section</th>
                    <th class="border border-gray-400 px-2 py-1 text-left">Track</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Present</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Absent</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Recorded</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Attendance %</th>
                </tr>
            </thead>
            <tbody>
                @foreach($students as $enrollment)
                    @php
                        $present = (int) $enrollment->present_count;
                        $absent = (int) $enrollment->absent_count;
                    @endphp
                    <tr>
                        <td class="border border-gray-400 px-2 py-1">{{ $loop->iteration }}</td>
                        <td class="border border-gray-400 px-2 py-1 whitespace-nowrap">{{ $enrollment->student->full_name }}</td>
                        <td class="border border-gray-400 px-2 py-1 whitespace-nowrap">{{ $enrollment->student->registration_number }}</td>
                        <td class="border border-gray-400 px-2 py-1">{{ $enrollment->student->roll_number ?? '—' }}</td>
                        {{-- The placement the attendance was taken under. --}}
                        <td class="border border-gray-400 px-2 py-1 whitespace-nowrap">{{ $enrollment->academicClass?->name ?? '—' }}</td>
                        <td class="border border-gray-400 px-2 py-1 whitespace-nowrap">{{ $enrollment->section?->name ?? 'No section' }}</td>
                        <td class="border border-gray-400 px-2 py-1">{{ $enrollment->academic_track }}</td>
                        <td class="border border-gray-400 px-2 py-1 text-right">{{ $present }}</td>
                        <td class="border border-gray-400 px-2 py-1 text-right">{{ $absent }}</td>
                        <td class="border border-gray-400 px-2 py-1 text-right">{{ $present + $absent }}</td>
                        <td class="border border-gray-400 px-2 py-1 text-right font-semibold">
                            {{ StudentAttendance::formatPercentage($present, $absent) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- Class summary -->
    <h3 class="text-sm font-semibold text-gray-900 mb-1">Class Summary</h3>

    @if($groupSummary === [])
        <p class="text-sm text-gray-700 mb-4">No classes match the selected filters.</p>
    @else
        <table class="w-full border-collapse text-xs mb-6">
            <thead>
                <tr>
                    <th class="border border-gray-400 px-2 py-1 text-left">Class</th>
                    <th class="border border-gray-400 px-2 py-1 text-left">Section</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Students</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Present</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Absent</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Recorded</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Attendance %</th>
                </tr>
            </thead>
            <tbody>
                @foreach($groupSummary as $group)
                    <tr>
                        <td class="border border-gray-400 px-2 py-1">{{ $group['class'] }}</td>
                        <td class="border border-gray-400 px-2 py-1">{{ $group['section'] }}</td>
                        <td class="border border-gray-400 px-2 py-1 text-right">{{ $group['students'] }}</td>
                        <td class="border border-gray-400 px-2 py-1 text-right">{{ $group['present'] }}</td>
                        <td class="border border-gray-400 px-2 py-1 text-right">{{ $group['absent'] }}</td>
                        <td class="border border-gray-400 px-2 py-1 text-right">{{ $group['recorded'] }}</td>
                        <td class="border border-gray-400 px-2 py-1 text-right font-semibold">{{ $percentageLabel($group['percentage']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- Period summary -->
    <h3 class="text-sm font-semibold text-gray-900 mb-1">Period Summary</h3>

    <table class="w-full border-collapse text-xs">
        <thead>
            <tr>
                <th class="border border-gray-400 px-2 py-1 text-left">Period</th>
                <th class="border border-gray-400 px-2 py-1 text-right">Present</th>
                <th class="border border-gray-400 px-2 py-1 text-right">Absent</th>
                <th class="border border-gray-400 px-2 py-1 text-right">Recorded</th>
                <th class="border border-gray-400 px-2 py-1 text-right">Attendance %</th>
            </tr>
        </thead>
        <tbody>
            @foreach($periodSummary as $row)
                <tr>
                    <td class="border border-gray-400 px-2 py-1">{{ $row['period'] }}</td>
                    <td class="border border-gray-400 px-2 py-1 text-right">{{ $row['present'] }}</td>
                    <td class="border border-gray-400 px-2 py-1 text-right">{{ $row['absent'] }}</td>
                    <td class="border border-gray-400 px-2 py-1 text-right">{{ $row['recorded'] }}</td>
                    <td class="border border-gray-400 px-2 py-1 text-right font-semibold">{{ $percentageLabel($row['percentage']) }}</td>
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
