@php
    use App\Models\StudentAttendance;

    $summaryParameters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

    $percentageLabel = fn (?float $percentage) => $percentage === null ? 'N/A' : number_format($percentage, 2).'%';

    $groupLabel = collect([
        $group['track'],
        $group['department']?->name,
        $group['academicClass']?->name,
        $group['section'] ? 'Section ' . $group['section']->name : null,
    ])->filter()->implode(' - ');
@endphp

<x-layout.admin title="Session Attendance Summary">
    <x-slot name="header">
        Session Attendance Summary
    </x-slot>

    <div class="space-y-6">
        <!-- Intro -->
        <div class="bg-white rounded-lg shadow-sm p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Session Attendance Summary</h2>
                <p class="text-sm text-gray-600 mt-1">
                    A whole academic session for one track. <span class="font-medium">Attendance Opportunities</span>
                    is what the paper registers could hold; <span class="font-medium">Recorded</span> is what has been
                    transcribed so far. The difference is work still to do, and is never counted as absence.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if($session)
                    <a href="{{ route('attendance.session-summary.print', $summaryParameters) }}"
                       target="_blank"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Print Session Summary
                    </a>

                    <a href="{{ route('attendance.session-summary.export', $summaryParameters) }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Export CSV
                    </a>
                @endif

                <a href="{{ route('attendance.reports') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap">
                    Monthly Reports
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Session Filters</h3>
            </div>

            <div class="px-6 py-4">
                {{-- Department -> Class -> Section narrow each other, the same
                     pattern the rest of the module uses. The track reloads the
                     form because it decides how many registers a day holds. --}}
                <form
                    method="GET"
                    action="{{ route('attendance.session-summary') }}"
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
                                @forelse($academicSessions as $option)
                                    <option value="{{ $option->id }}" {{ (int) $filters['academic_session_id'] === $option->id ? 'selected' : '' }}>
                                        {{ $option->name }}
                                    </option>
                                @empty
                                    <option value="">No academic sessions</option>
                                @endforelse
                            </select>
                        </div>

                        <!-- Academic Track -->
                        <div>
                            <label for="academic_track" class="block text-sm font-medium text-gray-700 mb-1">Academic Track</label>
                            {{-- No "all tracks": madrassa sits three registers
                                 a day and school one, so a combined figure
                                 would mean nothing. --}}
                            <select name="academic_track" id="academic_track"
                                @change="$el.form.submit()"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @foreach($academicTracks as $track)
                                    <option value="{{ $track }}" {{ $filters['academic_track'] === $track ? 'selected' : '' }}>{{ $track }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ count($periods) }} {{ count($periods) === 1 ? 'register' : 'registers' }} a day:
                                {{ implode(', ', $periods) }}
                            </p>
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
                    </div>

                    <div class="flex items-center space-x-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            Generate Summary
                        </button>
                        <a href="{{ route('attendance.session-summary') }}"
                           class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @if($session === null)
            <div class="bg-white rounded-lg shadow-sm px-6 py-12 text-center">
                <h3 class="text-sm font-medium text-gray-900">No academic session to report on.</h3>
                <p class="mt-1 text-sm text-gray-500">Create an academic session before running a session summary.</p>
            </div>
        @else
            <!-- Summary cards -->
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-1">
                    {{ $session->name }} &middot; {{ $groupLabel }}
                </h3>
                <p class="text-sm text-gray-600 mb-3">
                    {{ $sessionStart->format('d M, Y') }} to {{ $sessionEnd->format('d M, Y') }}
                    &middot; {{ $totals['session_teaching_days'] }} teaching days in the session
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <p class="text-sm font-medium text-gray-600 mb-1">Total Students</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $totals['students'] }}</p>
                        <p class="text-sm text-gray-600 mt-2">In this group</p>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <p class="text-sm font-medium text-gray-600 mb-1">Teaching Days</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $totals['session_teaching_days'] }}</p>
                        <p class="text-sm text-gray-600 mt-2">Weekdays in the session</p>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <p class="text-sm font-medium text-gray-600 mb-1">Attendance Opportunities</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $totals['opportunities'] }}</p>
                        <p class="text-sm text-gray-600 mt-2">
                            All students &times; {{ count($periods) }} {{ count($periods) === 1 ? 'register' : 'registers' }} a day
                        </p>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <p class="text-sm font-medium text-gray-600 mb-1">Total Recorded</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $totals['recorded'] }}</p>
                        <p class="text-sm text-amber-700 mt-2">{{ $totals['unrecorded'] }} not entered yet</p>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <p class="text-sm font-medium text-gray-600 mb-1">Present / Absent</p>
                        <p class="text-3xl font-bold">
                            <span class="text-green-600">{{ $totals['present'] }}</span>
                            <span class="text-gray-400 text-xl">/</span>
                            <span class="text-red-600">{{ $totals['absent'] }}</span>
                        </p>
                        <p class="text-sm text-gray-600 mt-2">Of recorded attendance</p>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <p class="text-sm font-medium text-gray-600 mb-1">Overall Attendance</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $percentageLabel($totals['percentage']) }}</p>
                        <p class="text-sm text-gray-600 mt-2">Present of recorded</p>
                    </div>
                </div>

                <p class="text-sm text-gray-500 mt-3">
                    Attendance percentage is Present divided by Recorded, so attendance that has not been transcribed
                    yet does not count against a student. Sundays are never an opportunity.
                </p>
            </div>

            @if($totals['students'] > 0 && $totals['recorded'] === 0)
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <p class="text-sm text-amber-800">
                        No attendance has been transcribed for this session yet. The students below are listed with
                        their attendance opportunities so the outstanding paper registers can be found.
                    </p>
                </div>
            @endif

            <!-- Students -->
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
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reg. No.</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No.</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Track</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Opportunities</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unrecorded</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Present</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Absent</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance %</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($students as $student)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <a href="{{ route('students.show', $student['student_id']) }}"
                                               class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                                {{ $student['full_name'] }}
                                            </a>
                                            @if($student['placements'] > 1)
                                                {{-- Promoted mid-session: the figures cover
                                                     every placement they held on this track. --}}
                                                <span class="ml-1 inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800"
                                                      title="Promoted during this session. The figures cover all {{ $student['placements'] }} placements.">
                                                    {{ $student['placements'] }} placements
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student['registration_number'] }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student['roll_number'] ?? '—' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student['department'] }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student['class'] }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student['section'] }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student['academic_track'] }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                            {{ $student['opportunities'] }}
                                            <span class="block text-xs text-gray-500">{{ $student['teaching_days'] }} days</span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-right text-gray-900">{{ $student['recorded'] }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-right {{ $student['unrecorded'] > 0 ? 'text-amber-700 font-semibold' : 'text-gray-400' }}">
                                            {{ $student['unrecorded'] }}
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-right font-semibold text-green-700">{{ $student['present'] }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-right font-semibold text-red-700">{{ $student['absent'] }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900">
                                            {{ StudentAttendance::formatPercentage($student['present'], $student['absent']) }}
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="{{ route('students.attendance', [
                                                'student' => $student['student_id'],
                                                'academic_track' => $student['academic_track'],
                                                'academic_session_id' => $session->id,
                                            ]) }}" class="text-blue-600 hover:text-blue-800">
                                                View Attendance History
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200">
                        {{ $students->links() }}
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No students found for the selected filters.</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            No {{ $filters['academic_track'] }} enrollment in {{ $session->name }} matches this department, class and section.
                        </p>
                    </div>
                @endif
            </div>

            <!-- Monthly breakdown -->
            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Monthly Breakdown</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Only the months this session covers. A month the session starts or ends inside counts
                        from the overlap alone.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Teaching Days</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Opportunities</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Present</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Absent</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance %</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($months as $month)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $month['label'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900">{{ $month['session_teaching_days'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900">{{ $month['opportunities'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900">{{ $month['recorded'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-green-700">{{ $month['present'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-red-700">{{ $month['absent'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-gray-900">{{ $percentageLabel($month['percentage']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3 border-t border-gray-200">
                    <p class="text-xs text-gray-500">
                        Teaching Days is the calendar length of the month inside the session.
                        Opportunities covers every student in this group.
                    </p>
                </div>
            </div>
        @endif
    </div>
</x-layout.admin>
