@php
    use App\Models\StudentAttendance;

    $groupLabel = collect([
        $group['track'],
        $group['department']?->name,
        $group['academicClass']?->name,
        $group['section'] ? 'Section ' . $group['section']->name : 'All sections',
    ])->filter()->implode(' - ');
@endphp

<x-layout.print
    title="Attendance Sheet"
    :heading="'Monthly Attendance Register - ' . $monthLabel"
    :back="route('attendance.index', array_filter($filters, fn ($value) => $value !== null && $value !== ''))"
>
    <!-- What this register covers -->
    <table class="w-full text-xs mb-3">
        <tbody>
            <tr>
                <td class="py-0.5"><span class="font-semibold">Academic Session:</span> {{ $group['session']?->name ?? '—' }}</td>
                <td class="py-0.5"><span class="font-semibold">Track:</span> {{ $filters['academic_track'] }}</td>
                <td class="py-0.5"><span class="font-semibold">Department:</span> {{ $group['department']?->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="py-0.5"><span class="font-semibold">Class:</span> {{ $group['academicClass']?->name ?? '—' }}</td>
                <td class="py-0.5"><span class="font-semibold">Section:</span> {{ $group['section']?->name ?? 'All sections' }}</td>
                {{-- Always named: madrassa keeps three registers for the same
                     month, and a printed page has to say which one it is. --}}
                <td class="py-0.5"><span class="font-semibold">Period:</span> {{ $filters['attendance_period'] }}</td>
            </tr>
            <tr>
                <td class="py-0.5"><span class="font-semibold">Month:</span> {{ $monthLabel }}</td>
                <td class="py-0.5" colspan="2"><span class="font-semibold">Students:</span> {{ $sheet->count() }}</td>
            </tr>
        </tbody>
    </table>

    <p class="text-[10px] text-gray-600 mb-2">
        P = Present &nbsp;&middot;&nbsp; A = Absent &nbsp;&middot;&nbsp; OFF = Sunday, no attendance is taken
        &nbsp;&middot;&nbsp; blank = not yet entered
    </p>

    @if($sheet->isEmpty())
        <p class="text-sm text-gray-700">No active enrollments match this academic group.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[10px]">
                <thead>
                    <tr>
                        <th class="border border-gray-400 px-1 py-1 text-left">#</th>
                        <th class="border border-gray-400 px-1 py-1 text-left">Student Name</th>
                        <th class="border border-gray-400 px-1 py-1 text-left">Reg. No.</th>
                        <th class="border border-gray-400 px-1 py-1 text-left">Roll</th>
                        <th class="border border-gray-400 px-1 py-1 text-left">Section</th>
                        {{-- One column per calendar day, so 28, 29, 30 and 31
                             day months each print their own length. --}}
                        @foreach($days as $day)
                            <th class="border border-gray-400 px-0.5 py-1 text-center {{ $day['is_off_day'] ? 'bg-gray-200' : '' }}">
                                {{ $day['day'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($sheet as $enrollment)
                        <tr>
                            <td class="border border-gray-400 px-1 py-1">{{ $loop->iteration }}</td>
                            <td class="border border-gray-400 px-1 py-1 whitespace-nowrap">{{ $enrollment->student->full_name }}</td>
                            <td class="border border-gray-400 px-1 py-1 whitespace-nowrap">{{ $enrollment->student->registration_number }}</td>
                            <td class="border border-gray-400 px-1 py-1">{{ $enrollment->student->roll_number ?? '—' }}</td>
                            <td class="border border-gray-400 px-1 py-1 whitespace-nowrap">{{ $enrollment->section?->name ?? 'No section' }}</td>

                            @foreach($days as $day)
                                @if($day['is_off_day'])
                                    <td class="border border-gray-400 px-0.5 py-1 text-center bg-gray-200 text-[8px]">OFF</td>
                                @else
                                    @php
                                        $status = $cells[StudentAttendance::cellKey($enrollment->id, $day['date'])]['status'] ?? '';
                                    @endphp
                                    {{-- Blank where nothing was entered. An
                                         empty cell is not a status. --}}
                                    <td class="border border-gray-400 px-0.5 py-1 text-center font-semibold">
                                        @if($status === 'Present')
                                            P
                                        @elseif($status === 'Absent')
                                            A
                                        @endif
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-8 flex justify-between text-xs">
            <div>
                <div class="border-t border-gray-500 w-48 pt-1">Class Teacher</div>
            </div>
            <div>
                <div class="border-t border-gray-500 w-48 pt-1">Principal</div>
            </div>
        </div>
    @endif
</x-layout.print>
