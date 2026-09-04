@php
    use App\Models\StudentAttendance;

    // The filters as they stand, carried through the sort links so a
    // re-sort never drops the month being reported on.
    $reportParameters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

    $percentageLabel = fn (?float $percentage) => $percentage === null ? 'N/A' : number_format($percentage, 2).'%';

    $sortLabels = [
        'student' => 'Student',
        'registration' => 'Registration No.',
        'roll' => 'Roll No.',
        'present' => 'Present',
        'absent' => 'Absent',
        'percentage' => 'Attendance %',
    ];

    // A sort link flips direction when it is already the active one.
    $sortLink = function (string $sort) use ($reportParameters, $filters) {
        $direction = $filters['sort'] === $sort && $filters['direction'] === 'asc' ? 'desc' : 'asc';

        return route('attendance.reports', array_merge($reportParameters, [
            'sort' => $sort,
            'direction' => $direction,
        ]));
    };
@endphp

<x-layout.admin title="Attendance Reports">
    <x-slot name="header">
        Attendance Reports
    </x-slot>

    <div class="space-y-6">
        <!-- Intro -->
        <div class="bg-white rounded-lg shadow-sm p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Monthly Attendance Report</h2>
                <p class="text-sm text-gray-600 mt-1">
                    Totals for attendance that has actually been entered. A day with no record is not counted as
                    present or absent, so a low record count means the paper register is still waiting to be transcribed.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {{-- Both carry the filters exactly as they stand, so the
                     paper and the spreadsheet are the report on screen. --}}
                <a href="{{ route('attendance.reports.print', $reportParameters) }}"
                   target="_blank"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Print Report
                </a>

                <a href="{{ route('attendance.reports.export', $reportParameters) }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Export CSV
                </a>

                <a href="{{ route('attendance.session-summary', array_filter(['academic_session_id' => $filters['academic_session_id'], 'academic_track' => $filters['academic_track'], 'department_id' => $filters['department_id'], 'academic_class_id' => $filters['academic_class_id'], 'section_id' => $filters['section_id']])) }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Session Summary
                </a>

                <a href="{{ route('attendance.index') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Monthly Attendance Entry
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Report Filters</h3>
            </div>

            <div class="px-6 py-4">
                {{-- Department -> Class -> Section narrow each other, the same
                     pattern the entry sheet and the academic pages use. The
                     track reloads the form, because the periods it decides
                     are the server's to give. --}}
                <form
                    method="GET"
                    action="{{ route('attendance.reports') }}"
                    class="space-y-4"
                    x-data="{
                        departmentId: '{{ $filters['department_id'] }}',
                        academicClassId: '{{ $filters['academic_class_id'] }}',
                        sectionId: '{{ $filters['section_id'] }}',
                        classesByDepartment: {{ Js::from($classesByDepartment) }},
                        sectionsByClass: {{ Js::from($sectionsByClass) }},
                        get classes() {
                            return this.departmentId ? (this.classesByDepartment[this.departmentId] ?? []) : []
                        },
                        get sections() {
                            return this.academicClassId ? (this.sectionsByClass[this.academicClassId] ?? []) : []
                        },
                        onDepartmentChange() {
                            this.academicClassId = ''
                            this.sectionId = ''
                        },
                        onClassChange() { this.sectionId = '' },
                    }"
                >
                    <!-- Search -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input
                            type="text"
                            name="search"
                            id="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Search by student name, registration number, or roll number..."
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Academic Session -->
                        <div>
                            <label for="academic_session_id" class="block text-sm font-medium text-gray-700 mb-1">Academic Session</label>
                            <select name="academic_session_id" id="academic_session_id"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All sessions</option>
                                @foreach($academicSessions as $session)
                                    <option value="{{ $session->id }}" {{ (int) $filters['academic_session_id'] === $session->id ? 'selected' : '' }}>
                                        {{ $session->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Academic Track -->
                        <div>
                            <label for="academic_track" class="block text-sm font-medium text-gray-700 mb-1">Academic Track</label>
                            {{-- No "all tracks": school and madrassa sit
                                 different periods, so one number covering
                                 both would mean nothing. --}}
                            <select name="academic_track" id="academic_track"
                                @change="$el.form.submit()"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @foreach($academicTracks as $track)
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

                        <!-- Department -->
                        <div>
                            <label for="department_id" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                            <select name="department_id" id="department_id"
                                x-model="departmentId" @change="onDepartmentChange()"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All departments</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Class -->
                        <div>
                            <label for="academic_class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                            <select name="academic_class_id" id="academic_class_id"
                                x-model="academicClassId" @change="onClassChange()"
                                :disabled="! departmentId"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100">
                                <option value="">All classes</option>
                                <template x-for="option in classes" :key="option.id">
                                    <option :value="option.id" x-text="option.name"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-sm text-gray-500" x-show="! departmentId" x-cloak>Choose a department first.</p>
                        </div>

                        <!-- Section -->
                        <div>
                            <label for="section_id" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                            <select name="section_id" id="section_id"
                                x-model="sectionId"
                                :disabled="! academicClassId"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100">
                                <option value="">All sections</option>
                                <template x-for="option in sections" :key="option.id">
                                    <option :value="option.id" x-text="option.name"></option>
                                </template>
                            </select>
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

                    {{-- The sort travels with the filters so applying a filter
                         does not silently reset the column being read. --}}
                    <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                    <input type="hidden" name="direction" value="{{ $filters['direction'] }}">

                    <div class="flex items-center space-x-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            Generate Report
                        </button>
                        <a href="{{ route('attendance.reports') }}"
                           class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary cards -->
        <div>
            <h3 class="text-lg font-semibold text-gray-800 mb-3">
                {{ $filters['academic_track'] }} &middot; {{ $monthLabel }}
                @if($filters['attendance_period'] !== $allPeriodsValue)
                    &middot; {{ $filters['attendance_period'] }}
                @endif
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Students</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $summary['students'] }}</p>
                    <p class="text-sm text-gray-600 mt-2">In this academic group</p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Recorded</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $summary['recorded'] }}</p>
                    <p class="text-sm text-gray-600 mt-2">Attendance records entered</p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-600 mb-1">Present</p>
                    <p class="text-3xl font-bold text-green-600">{{ $summary['present'] }}</p>
                    <p class="text-sm text-gray-600 mt-2">Marked present</p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-600 mb-1">Absent</p>
                    <p class="text-3xl font-bold text-red-600">{{ $summary['absent'] }}</p>
                    <p class="text-sm text-gray-600 mt-2">Marked absent</p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-600 mb-1">Overall Attendance</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $percentageLabel($summary['percentage']) }}</p>
                    <p class="text-sm text-gray-600 mt-2">Present of recorded</p>
                </div>
            </div>

            <p class="text-sm text-gray-500 mt-3">
                Attendance percentage is Present divided by Recorded. Weekends, and days nobody has entered yet,
                are not in the denominator.
            </p>
        </div>

        @if($summary['students'] > 0 && $summary['recorded'] === 0)
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                <p class="text-sm text-amber-800">
                    No attendance has been recorded for the selected month. The students below are listed so the
                    remaining paper registers can be found; their totals are zero because nothing has been entered yet.
                </p>
            </div>
        @endif

        <!-- Student summary -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Student Summary</h3>
                @if($students->total() > 0)
                    <p class="text-sm text-gray-600 mt-1">
                        Showing {{ $students->firstItem() }} to {{ $students->lastItem() }} of {{ $students->total() }} students
                    </p>
                @endif
            </div>

            @if($students->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                @foreach(['student' => 'Student', 'registration' => 'Registration No.', 'roll' => 'Roll No.'] as $key => $label)
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="{{ $sortLink($key) }}" class="inline-flex items-center hover:text-gray-700">
                                            {{ $label }}
                                            @if($filters['sort'] === $key)
                                                <span class="ml-1">{{ $filters['direction'] === 'asc' ? '&uarr;' : '&darr;' }}</span>
                                            @endif
                                        </a>
                                    </th>
                                @endforeach

                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Track</th>

                                @foreach(['present' => 'Present', 'absent' => 'Absent'] as $key => $label)
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="{{ $sortLink($key) }}" class="inline-flex items-center hover:text-gray-700">
                                            {{ $label }}
                                            @if($filters['sort'] === $key)
                                                <span class="ml-1">{{ $filters['direction'] === 'asc' ? '&uarr;' : '&darr;' }}</span>
                                            @endif
                                        </a>
                                    </th>
                                @endforeach

                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded</th>

                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <a href="{{ $sortLink('percentage') }}" class="inline-flex items-center hover:text-gray-700">
                                        Attendance %
                                        @if($filters['sort'] === 'percentage')
                                            <span class="ml-1">{{ $filters['direction'] === 'asc' ? '&uarr;' : '&darr;' }}</span>
                                        @endif
                                    </a>
                                </th>

                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($students as $enrollment)
                                @php
                                    $present = (int) $enrollment->present_count;
                                    $absent = (int) $enrollment->absent_count;
                                    $recorded = $present + $absent;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <a href="{{ route('students.show', $enrollment->student_id) }}"
                                           class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                            {{ $enrollment->student->full_name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->student->registration_number }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->student->roll_number ?? '—' }}</td>

                                    {{-- The placement the attendance was taken
                                         under, from the enrollment: a promoted
                                         student's old month still reports under
                                         the class it happened in. --}}
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->academicClass?->name ?? '—' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->section?->name ?? 'No section' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->academic_track }}</td>

                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right font-semibold text-green-700">{{ $present }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right font-semibold text-red-700">{{ $absent }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right text-gray-900">{{ $recorded }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900">
                                        {{ StudentAttendance::formatPercentage($present, $absent) }}
                                    </td>

                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                        {{-- The existing history page, opened on
                                             the same track, session and month. --}}
                                        <a href="{{ route('students.attendance', [
                                            'student' => $enrollment->student_id,
                                            'academic_track' => $enrollment->academic_track,
                                            'academic_session_id' => $enrollment->academic_session_id,
                                            'month' => $filters['month'],
                                            'year' => $filters['year'],
                                        ]) }}" class="text-blue-600 hover:text-blue-800">
                                            View Attendance History
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $students->links() }}
                </div>
            @else
                <!-- Empty State -->
                <div class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No students found for the selected filters.</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        No {{ $filters['academic_track'] }} enrollment matches this session, department, class and section.
                    </p>
                </div>
            @endif
        </div>

        <!-- Class and section summary -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Class Summary</h3>
                <p class="text-sm text-gray-600 mt-1">Every class and section the filters cover, across all pages.</p>
            </div>

            @if($groupSummary !== [])
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Present</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Absent</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance %</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($groupSummary as $group)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $group['class'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $group['section'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900">{{ $group['students'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-green-700">{{ $group['present'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-red-700">{{ $group['absent'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900">{{ $group['recorded'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-gray-900">{{ $percentageLabel($group['percentage']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-6 py-8 text-center text-sm text-gray-500">
                    No classes match the selected filters.
                </div>
            @endif
        </div>

        <!-- Period summary -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Period Summary</h3>
                <p class="text-sm text-gray-600 mt-1">
                    @if(count($availablePeriods) > 1)
                        Where attendance is weaker across the {{ $filters['academic_track'] }} periods being reported on.
                    @else
                        School sits once a day, so the report covers the Morning register alone.
                    @endif
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Present</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Absent</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance %</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($periodSummary as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ $row['period'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-green-700">{{ $row['present'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-red-700">{{ $row['absent'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900">{{ $row['recorded'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-gray-900">{{ $percentageLabel($row['percentage']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layout.admin>
