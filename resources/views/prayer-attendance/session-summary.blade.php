@php
    use App\Models\StudentPrayerAttendance;

    $hasFilters = collect($filters)
        ->except('academic_session_id')
        ->filter(fn ($value) => $value !== null && $value !== '')
        ->isNotEmpty();
@endphp

<x-layout.admin title="Prayer Session Summary">
    <x-slot name="header">
        Prayer Attendance — Session Summary
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center gap-4">
            <a href="{{ route('prayer-attendance.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Prayer Attendance
            </a>

            <a href="{{ route('prayer-attendance.reports') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Monthly Report
            </a>
        </div>

        <!-- Intro -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-xl font-semibold text-gray-800">
                {{ $session?->name ?? 'Academic Session' }} — Prayer Attendance Summary
            </h2>
            <p class="text-sm text-gray-600 mt-1">
                {{ collect([
                    $heading['department']?->name,
                    $heading['academicClass']?->name,
                    $heading['section'] ? 'Section ' . $heading['section']->name : null,
                ])->filter()->implode(' · ') ?: 'All Madrassa students' }}
            </p>
            {{-- Expected is per student, not per calendar: a student who
                 joined in November is not marked down for September. --}}
            <p class="text-sm text-gray-500 mt-2">
                Expected prayers are counted from each student's own Madrassa enrollment period inside the session,
                excluding Sundays, at five prayers per working day. A prayer nobody has transcribed yet
                is <span class="font-medium">Unrecorded</span>, never an absence, and percentages are Present over Recorded.
            </p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                {{-- Department -> Class -> Section narrow each other, the same
                     pattern the prayer sheet and monthly report use. --}}
                <form
                    method="GET"
                    action="{{ route('prayer-attendance.session-summary') }}"
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

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Academic Session -->
                        <div>
                            <label for="academic_session_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Academic Session <span class="text-red-500">*</span>
                            </label>
                            <select name="academic_session_id" id="academic_session_id"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select session</option>
                                @foreach($academicSessions as $option)
                                    <option value="{{ $option->id }}" {{ (int) $filters['academic_session_id'] === $option->id ? 'selected' : '' }}>
                                        {{ $option->name }}
                                    </option>
                                @endforeach
                            </select>
                            {{-- Required: without a session there is no period
                                 to count expected prayers over. --}}
                            <p class="mt-1 text-sm text-gray-500">The session is the report.</p>
                        </div>

                        <!-- Department -->
                        <div>
                            <label for="department_id" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                            <select name="department_id" id="department_id"
                                x-model="departmentId" @change="onDepartmentChange()"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Departments</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Class -->
                        <div>
                            <label for="academic_class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                            <select name="academic_class_id" id="academic_class_id"
                                x-model="academicClassId" @change="onClassChange()" :disabled="! departmentId"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100">
                                <option value="">All Classes</option>
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
                                x-model="sectionId" :disabled="! academicClassId || sections.length === 0"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100">
                                <option value="">All Sections</option>
                                <template x-for="option in sections" :key="option.id">
                                    <option :value="option.id" x-text="option.name"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Run Session Summary
                        </button>
                        @if($hasFilters)
                            <a href="{{ route('prayer-attendance.session-summary', ['academic_session_id' => $filters['academic_session_id']]) }}"
                               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                Clear Filters
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        @if($session === null)
            <div class="bg-white rounded-lg shadow-sm px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Choose an academic session to run the summary.</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Expected prayers are counted over the session, so one has to be selected.
                </p>
            </div>
        @else
            <!-- Session totals -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-600 mb-1">Madrassa Students</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $group['students'] }}</p>
                    <p class="text-sm text-gray-600 mt-2">
                        {{ $group['students_with_records'] }} with records &middot; {{ $group['students_without_records'] }} without
                    </p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-600 mb-1">Recorded Prayers</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $group['recorded'] }}</p>
                    <p class="text-sm text-gray-600 mt-2">
                        of {{ $group['expected'] }} expected &middot; {{ $group['unrecorded'] }} unrecorded
                    </p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-600 mb-1">Present / Absent</p>
                    <p class="text-3xl font-bold text-gray-900">
                        <span class="text-green-700">{{ $group['present'] }}</span>
                        <span class="text-gray-400 text-xl">/</span>
                        <span class="text-red-700">{{ $group['absent'] }}</span>
                    </p>
                    <p class="text-sm text-gray-600 mt-2">Of the recorded prayers</p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-600 mb-1">Session Attendance</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $group['percentage'] }}</p>
                    {{-- From the session's own totals, never an average of
                         the students' percentages. --}}
                    <p class="text-sm text-gray-600 mt-2">Present over recorded</p>
                </div>
            </div>

            <!-- Five-prayer session summary -->
            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Prayer Summary</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ $session->name }} &middot;
                        {{ \Illuminate\Support\Carbon::parse($sessionStart)->format('d M, Y') }}
                        to {{ \Illuminate\Support\Carbon::parse($sessionEnd)->format('d M, Y') }}
                        @if($countedEnd < $sessionEnd)
                            {{-- The session is still running, so the summary
                                 stops at today rather than counting days
                                 nobody has reached yet - neither as prayers
                                 expected nor as prayers recorded. --}}
                            <span class="text-gray-500">
                                &middot; counted to
                                {{ \Illuminate\Support\Carbon::parse($countedEnd)->format('d M, Y') }}
                            </span>
                        @endif
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prayer</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expected</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unrecorded</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Present</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Absent</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance %</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            {{-- In the order they are prayed, never alphabetically. --}}
                            @foreach($prayers as $prayer)
                                @php
                                    $prayerRow = $group['by_prayer'][$prayer];
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded bg-gray-100 text-gray-700 text-xs mr-2">{{ $prayerInitials[$prayer] }}</span>
                                        {{ $prayer }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $prayerRow['expected'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $prayerRow['recorded'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $prayerRow['unrecorded'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-green-700 font-medium">{{ $prayerRow['present'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-red-700 font-medium">{{ $prayerRow['absent'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900">{{ $prayerRow['percentage'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900">All prayers</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900">{{ $group['expected'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900">{{ $group['recorded'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-500">{{ $group['unrecorded'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-green-700">{{ $group['present'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-red-700">{{ $group['absent'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900">{{ $group['percentage'] }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Monthly breakdown -->
            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Monthly Breakdown</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Only the months this session runs over. Each month's expected count respects every student's
                        own enrollment period.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Expected</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unrecorded</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Present</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Absent</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance %</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($monthly as $month)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $month['label'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ $month['expected'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ $month['recorded'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-right">{{ $month['unrecorded'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-green-700 font-medium text-right">{{ $month['present'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-red-700 font-medium text-right">{{ $month['absent'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900 text-right">{{ $month['percentage'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Student-wise session report -->
            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Student-wise Session Report</h3>
                    @if($students->total() > 0)
                        <p class="text-sm text-gray-600 mt-1">
                            Showing {{ $students->firstItem() }} to {{ $students->lastItem() }} of {{ $students->total() }} Madrassa students
                        </p>
                    @endif
                </div>

                @if($students->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full border-separate border-spacing-0">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" rowspan="2"
                                        class="sticky left-0 z-20 bg-gray-50 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-r border-gray-200 min-w-[14rem]">
                                        Student
                                    </th>
                                    <th scope="col" rowspan="2" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200">Class</th>
                                    <th scope="col" rowspan="2" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200">Section</th>
                                    @foreach($prayers as $prayer)
                                        <th scope="col" colspan="2" class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-l border-gray-200">
                                            {{ $prayer }}
                                        </th>
                                    @endforeach
                                    <th scope="col" rowspan="2" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-l border-gray-200">Expected</th>
                                    <th scope="col" rowspan="2" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200">Recorded</th>
                                    <th scope="col" rowspan="2" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200">Unrecorded</th>
                                    <th scope="col" rowspan="2" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200">Present</th>
                                    <th scope="col" rowspan="2" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200">Absent</th>
                                    <th scope="col" rowspan="2" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200">Attendance %</th>
                                </tr>
                                <tr>
                                    @foreach($prayers as $prayer)
                                        <th scope="col" class="px-2 py-1 text-center text-[10px] font-medium text-green-700 uppercase border-b border-l border-gray-200">P</th>
                                        <th scope="col" class="px-2 py-1 text-center text-[10px] font-medium text-red-700 uppercase border-b border-gray-200">A</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                {{-- One row per Madrassa placement in the session.
                                     A student the register never mentions still
                                     appears, with zeroes and N/A: nothing recorded
                                     is not the same as nothing attended. --}}
                                @foreach($students as $studentRow)
                                    @php
                                        $present = (int) $studentRow->present;
                                        $absent = (int) $studentRow->absent;
                                        $recorded = (int) $studentRow->recorded;
                                        $expected = $expectedByEnrollment[(int) $studentRow->enrollment_id] ?? 0;
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="sticky left-0 z-10 bg-white px-4 py-2 border-b border-r border-gray-200">
                                            <div class="flex items-baseline gap-2">
                                                <span class="text-xs text-gray-400">{{ $loop->iteration + $students->firstItem() - 1 }}</span>
                                                {{-- Opens the existing history page for
                                                     this student, on the reported
                                                     session. Nothing is duplicated. --}}
                                                <a href="{{ route('students.prayer-attendance', [
                                                        'student' => $studentRow->student_id,
                                                        'academic_session_id' => $filters['academic_session_id'],
                                                   ]) }}"
                                                   class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                                    {{ $studentRow->full_name }}
                                                </a>
                                            </div>
                                            <div class="text-xs text-gray-500 ml-6">
                                                {{ $studentRow->registration_number }}
                                                @if($studentRow->roll_number)
                                                    &middot; Roll {{ $studentRow->roll_number }}
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 border-b border-gray-200">{{ $studentRow->class_name ?? 'N/A' }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 border-b border-gray-200">{{ $studentRow->section_name ?? 'No section' }}</td>

                                        @foreach($prayers as $prayer)
                                            @php
                                                $presentColumn = strtolower($prayer).'_'.strtolower(StudentPrayerAttendance::STATUS_PRESENT);
                                                $absentColumn = strtolower($prayer).'_'.strtolower(StudentPrayerAttendance::STATUS_ABSENT);
                                            @endphp
                                            <td class="px-2 py-2 text-center text-sm text-green-700 border-b border-l border-gray-200">{{ (int) $studentRow->{$presentColumn} }}</td>
                                            <td class="px-2 py-2 text-center text-sm text-red-700 border-b border-gray-200">{{ (int) $studentRow->{$absentColumn} }}</td>
                                        @endforeach

                                        <td class="px-3 py-2 text-center text-sm text-gray-900 border-b border-l border-gray-200">{{ $expected }}</td>
                                        <td class="px-3 py-2 text-center text-sm text-gray-900 border-b border-gray-200">{{ $recorded }}</td>
                                        <td class="px-3 py-2 text-center text-sm text-gray-500 border-b border-gray-200">{{ max(0, $expected - $recorded) }}</td>
                                        <td class="px-3 py-2 text-center text-sm font-medium text-green-700 border-b border-gray-200">{{ $present }}</td>
                                        <td class="px-3 py-2 text-center text-sm font-medium text-red-700 border-b border-gray-200">{{ $absent }}</td>
                                        <td class="px-3 py-2 text-center text-sm font-semibold text-gray-900 border-b border-gray-200">
                                            {{-- N/A rather than 0% when nothing was
                                                 recorded: no register, no verdict. --}}
                                            {{ StudentPrayerAttendance::formatPercentage($present, $absent) }}
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
                        <h3 class="text-sm font-medium text-gray-900">No Madrassa students match these filters.</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Only students holding a Madrassa enrollment in {{ $session->name }} appear here.
                            School students are not part of prayer attendance.
                        </p>
                        @if($hasFilters)
                            <div class="mt-6">
                                <a href="{{ route('prayer-attendance.session-summary', ['academic_session_id' => $filters['academic_session_id']]) }}"
                                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Clear all filters
                                </a>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-layout.admin>
