@php
    $periodLabel = $filters['attendance_period'] === $allPeriodsValue
        ? 'All periods'
        : $filters['attendance_period'];

    // The placement on the track being printed, so the header names the
    // class this record belongs to rather than every class the student has.
    $placement = $student->academicEnrollments
        ->where('academic_track', $filters['academic_track'])
        ->sortByDesc('start_date')
        ->first();
@endphp

<x-layout.print
    title="Attendance History"
    :heading="'Student Attendance History - ' . $monthLabel"
    :back="route('students.attendance', array_merge(['student' => $student->id], array_filter($filters, fn ($value) => $value !== null && $value !== '')))"
>
    <!-- Student -->
    <table class="w-full text-xs mb-3">
        <tbody>
            <tr>
                <td class="py-0.5"><span class="font-semibold">Student:</span> {{ $student->full_name }}</td>
                <td class="py-0.5"><span class="font-semibold">Registration No:</span> {{ $student->registration_number }}</td>
                <td class="py-0.5"><span class="font-semibold">Roll No:</span> {{ $student->roll_number ?? '—' }}</td>
            </tr>
            <tr>
                <td class="py-0.5"><span class="font-semibold">Track:</span> {{ $filters['academic_track'] }}</td>
                <td class="py-0.5"><span class="font-semibold">Class:</span> {{ $placement?->academicClass?->name ?? '—' }}</td>
                <td class="py-0.5"><span class="font-semibold">Section:</span> {{ $placement?->section?->name ?? 'No section' }}</td>
            </tr>
            <tr>
                <td class="py-0.5"><span class="font-semibold">Academic Session:</span> {{ $placement?->academicSession?->name ?? 'All sessions' }}</td>
                <td class="py-0.5"><span class="font-semibold">Month:</span> {{ $monthLabel }}</td>
                <td class="py-0.5"><span class="font-semibold">Period:</span> {{ $periodLabel }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Totals for what was printed -->
    <table class="w-full text-xs mb-3">
        <tbody>
            <tr>
                <td class="py-0.5"><span class="font-semibold">Total Recorded:</span> {{ $summary['total'] }}</td>
                <td class="py-0.5"><span class="font-semibold">Present:</span> {{ $summary['present'] }}</td>
                <td class="py-0.5"><span class="font-semibold">Absent:</span> {{ $summary['absent'] }}</td>
            </tr>
        </tbody>
    </table>

    @if($records->isEmpty())
        <p class="text-sm text-gray-700">No attendance records found for the selected filters.</p>
    @else
        <table class="w-full border-collapse text-xs">
            <thead>
                <tr>
                    <th class="border border-gray-400 px-2 py-1 text-left">#</th>
                    <th class="border border-gray-400 px-2 py-1 text-left">Date</th>
                    <th class="border border-gray-400 px-2 py-1 text-left">Day</th>
                    <th class="border border-gray-400 px-2 py-1 text-left">Period</th>
                    <th class="border border-gray-400 px-2 py-1 text-left">Status</th>
                    <th class="border border-gray-400 px-2 py-1 text-left">Absence Reason</th>
                    <th class="border border-gray-400 px-2 py-1 text-left">Academic Placement</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $record)
                    @php($enrollment = $record->studentAcademicEnrollment)
                    <tr>
                        <td class="border border-gray-400 px-2 py-1">{{ $loop->iteration }}</td>
                        <td class="border border-gray-400 px-2 py-1 whitespace-nowrap">{{ $record->attendance_date->format('d M, Y') }}</td>
                        <td class="border border-gray-400 px-2 py-1">{{ $record->attendance_date->format('l') }}</td>
                        <td class="border border-gray-400 px-2 py-1">{{ $record->attendance_period }}</td>
                        <td class="border border-gray-400 px-2 py-1 font-semibold">{{ $record->status }}</td>
                        {{-- Only an absence carries a reason. --}}
                        <td class="border border-gray-400 px-2 py-1">{{ $record->isPresent() ? '' : ($record->absence_reason ?? '') }}</td>
                        {{-- The placement the record was taken under, not the
                             student's current one. --}}
                        <td class="border border-gray-400 px-2 py-1 whitespace-nowrap">
                            {{ $enrollment?->academicClass?->name ?? '—' }}
                            &middot; {{ $enrollment?->section?->name ?? 'No section' }}
                            &middot; {{ $enrollment?->academicSession?->name ?? '—' }}
                        </td>
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
    @endif
</x-layout.print>
