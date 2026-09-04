<x-layout.admin title="Attendance History">
    <x-slot name="header">
        Attendance History
    </x-slot>

    <div class="space-y-6">
        <!-- Navigation -->
        <div class="flex flex-wrap items-center gap-4">
            <a href="{{ route('students.show', $student->id) }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Student Profile
            </a>

            @if($activeEnrollment = $student->academicEnrollments->firstWhere('status', 'Active'))
                <a href="{{ route('academics.enrollments.show', $activeEnrollment->id) }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    View Academic Record
                </a>
            @endif

            {{-- The same filters, on paper. --}}
            <a href="{{ route('students.attendance.print', array_merge(['student' => $student->id], array_filter($filters, fn ($value) => $value !== null && $value !== ''))) }}"
               target="_blank"
               class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print
            </a>
        </div>

        <!-- Student -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center gap-6">
                <div class="flex-shrink-0">
                    <div class="w-20 h-20 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden">
                        @if($student->photo && \Storage::disk('public')->exists($student->photo))
                            <img src="{{ asset('storage/' . $student->photo) }}" class="w-20 h-20 rounded-full object-cover" alt="{{ $student->full_name }}">
                        @else
                            <span class="text-2xl font-medium text-gray-600">{{ mb_strtoupper(mb_substr($student->full_name, 0, 1)) }}</span>
                        @endif
                    </div>
                </div>

                <div class="flex-1">
                    <h2 class="text-xl font-semibold text-gray-800">{{ $student->full_name }}</h2>
                    <div class="mt-1 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                        <span>Registration No: {{ $student->registration_number }}</span>
                        <span>Roll No: {{ $student->roll_number ?? '—' }}</span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full
                            {{ $student->student_status === 'Active' ? 'bg-green-100 text-green-800' :
                               ($student->student_status === 'Passed' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800') }}">
                            {{ $student->student_status }}
                        </span>
                        <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                            {{ $student->student_type }}
                        </span>
                    </div>
                </div>

                {{-- The placement each track is currently on, so the history
                     is read against where the student actually sits. --}}
                <div class="flex flex-col gap-2">
                    @forelse($student->academicEnrollments->where('status', 'Active') as $current)
                        <div class="border border-gray-200 rounded-lg px-4 py-2 text-sm">
                            <span class="font-semibold text-gray-800">{{ $current->academic_track }}</span>
                            <span class="text-gray-600">
                                &middot; {{ $current->academicClass?->name ?? '—' }}
                                &middot; {{ $current->section?->name ?? 'No section' }}
                            </span>
                            <div class="text-xs text-gray-500">{{ $current->academicSession?->name ?? '—' }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No active enrollment.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Filters</h3>
            </div>

            <div class="px-6 py-4">
                <form method="GET" action="{{ route('students.attendance', $student->id) }}" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Academic Session -->
                        <div>
                            <label for="academic_session_id" class="block text-sm font-medium text-gray-700 mb-1">Academic Session</label>
                            <select name="academic_session_id" id="academic_session_id"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All sessions</option>
                                @foreach($academicSessions as $session)
                                    <option value="{{ $session->id }}" {{ $filters['academic_session_id'] === $session->id ? 'selected' : '' }}>
                                        {{ $session->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Track -->
                        <div>
                            <label for="academic_track" class="block text-sm font-medium text-gray-700 mb-1">Academic Track</label>
                            {{-- Reloads the form: the track decides which
                                 periods exist, and that list is the server's
                                 to give rather than the browser's to keep. --}}
                            <select name="academic_track" id="academic_track"
                                @change="$el.form.submit()"
                                x-data
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @foreach($tracks as $track)
                                    <option value="{{ $track }}" {{ $filters['academic_track'] === $track ? 'selected' : '' }}>{{ $track }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Attendance Period -->
                        <div>
                            <label for="attendance_period" class="block text-sm font-medium text-gray-700 mb-1">Attendance Period</label>
                            <select name="attendance_period" id="attendance_period"
                                @if(count($availablePeriods) <= 1) disabled @endif
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100">
                                @if(count($availablePeriods) > 1)
                                    <option value="{{ $allPeriodsValue }}" {{ $filters['attendance_period'] === $allPeriodsValue ? 'selected' : '' }}>
                                        All Periods
                                    </option>
                                @endif
                                @foreach($availablePeriods as $period)
                                    <option value="{{ $period }}" {{ $filters['attendance_period'] === $period ? 'selected' : '' }}>{{ $period }}</option>
                                @endforeach
                            </select>
                            @if(count($availablePeriods) === 1)
                                <p class="mt-1 text-xs text-gray-500">School attendance is recorded once a day, in the Morning.</p>
                            @endif
                        </div>

                        <!-- Month and Year -->
                        <div class="grid grid-cols-2 gap-4 md:col-span-2">
                            <div>
                                <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                                <select name="month" id="month"
                                    class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    @foreach($months as $number => $name)
                                        <option value="{{ $number }}" {{ $filters['month'] === $number ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                                <select name="year" id="year"
                                    class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    @foreach($years as $year)
                                        <option value="{{ $year }}" {{ $filters['year'] === $year ? 'selected' : '' }}>{{ $year }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Apply Filters
                        </button>
                        <a href="{{ route('students.attendance', $student->id) }}"
                           class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach([
                ['label' => 'Total Recorded', 'value' => $summary['total'], 'note' => 'Attendance records on file'],
                ['label' => 'Present', 'value' => $summary['present'], 'note' => 'Marked present'],
                ['label' => 'Absent', 'value' => $summary['absent'], 'note' => 'Marked absent'],
            ] as $card)
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-600 mb-1">{{ $card['label'] }}</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $card['value'] }}</p>
                    <p class="text-sm text-gray-600 mt-2">{{ $card['note'] }}</p>
                </div>
            @endforeach
        </div>

        @php
            $summaryScope = $filters['academic_track'].' attendance for '.$monthLabel
                .($filters['attendance_period'] === $allPeriodsValue ? '' : ', '.$filters['attendance_period'].' only');
        @endphp

        <p class="text-sm text-gray-500 -mt-2">
            Counts cover {{ $summaryScope }}.
            Days that were never transcribed are not counted: they are not attendance records.
        </p>

        <!-- Month overview -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">{{ $monthLabel }} at a glance</h3>
                <p class="text-sm text-gray-600 mt-1">
                    Every {{ $filters['academic_track'] }} period, whatever the table below is filtered to.
                    A blank cell means no attendance was recorded for that day.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Day</th>
                            @foreach($availablePeriods as $period)
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $period }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($days as $day)
                            <tr class="{{ $day['is_off_day'] ? 'bg-amber-50' : 'hover:bg-gray-50' }}">
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{{ $day['day'] }}</td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600">{{ $day['weekday'] }}</td>

                                @foreach($availablePeriods as $period)
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        @if($day['is_off_day'])
                                            <span class="text-xs font-medium text-amber-700">OFF</span>
                                        @else
                                            @php($record = $overview[$day['date']][$period] ?? null)
                                            @if($record === null)
                                                {{-- Never transcribed. Not a status. --}}
                                                <span class="inline-block w-5 h-5 rounded border-2 bg-gray-100 border-gray-200" title="No record"></span>
                                            @else
                                                <span class="inline-block w-5 h-5 rounded border-2 {{ $record->isPresent() ? 'bg-green-500 border-green-600' : 'bg-red-500 border-red-600' }}"
                                                      title="{{ $record->status }}{{ $record->isPresent() ? '' : ' - ' . $record->absence_reason }}"></span>
                                            @endif
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- History -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Attendance Records</h3>
                @if($records->total() > 0)
                    <p class="text-sm text-gray-600 mt-1">
                        Showing {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }} records
                    </p>
                @endif
            </div>

            @if($records->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Day</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Academic Context</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($records as $record)
                                @php($enrollment = $record->studentAcademicEnrollment)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $record->attendance_date->format('d M, Y') }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{ $record->attendance_date->format('l') }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        {{-- Always shown: madrassa holds three
                                             separate records for one day, and
                                             the period is what tells them
                                             apart. --}}
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                            {{ $record->attendance_period }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $record->statusBadgeClasses() }}">
                                            {{ $record->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-900">
                                        {{-- Only an absence has a reason. A
                                             present record shows nothing, even
                                             if one was once entered. --}}
                                        {{ $record->isPresent() ? '—' : ($record->absence_reason ?? '—') }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{-- The placement the record was taken
                                             under, not the student's current
                                             one: a promotion must not rewrite
                                             what already happened. --}}
                                        <div class="text-gray-900">{{ $enrollment?->academic_track }} &middot; {{ $enrollment?->academicClass?->name ?? '—' }}</div>
                                        <div class="text-xs">
                                            {{ $enrollment?->academicSession?->name ?? '—' }}
                                            &middot; {{ $enrollment?->department?->name ?? '—' }}
                                            &middot; {{ $enrollment?->section?->name ?? 'No section' }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $records->links() }}
                </div>
            @else
                <!-- Empty State -->
                <div class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No attendance records found for the selected filters.</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Attendance is entered from the paper register on the monthly attendance sheet.
                        Nothing is recorded here just by looking.
                    </p>
                    <div class="mt-6">
                        <a href="{{ route('attendance.index') }}"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Open Monthly Attendance Entry
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-layout.admin>
