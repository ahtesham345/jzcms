@php
    $hasFilters = collect($filters)
        ->except('record_type')
        ->filter(fn ($value) => $value !== null && $value !== '')
        ->isNotEmpty();

    $isHifz = $recordType === \App\Models\MadrassaDailyRecord::TYPE_HIFZ;
@endphp

<x-layout.admin title="Hifz & Quran Reports">
    <x-slot name="header">
        Hifz &amp; Quran — Reports &amp; Progress
    </x-slot>

    <div class="space-y-6">
        <div>
            <a href="{{ route('hifz.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Hifz &amp; Quran
            </a>
        </div>

        <!-- Intro -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-xl font-semibold text-gray-800">{{ $recordType }} Progress Report</h2>
            <p class="text-sm text-gray-600 mt-1">
                What was recorded, exactly as it was recorded. Every figure here is a count of recorded days
                or a value copied from a daily record.
            </p>
            {{-- Said out loud on the page, not just in the code: the
                 quantities are free text and nothing adds them up. --}}
            <p class="text-sm text-gray-500 mt-2">
                Quran quantities are shown as written &mdash; &ldquo;1 page&rdquo;, &ldquo;half page&rdquo;,
                &ldquo;1/2 para&rdquo;. Nothing is totalled, converted or turned into a completion percentage.
            </p>
        </div>

        <!-- Group summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Madrassa Students</p>
                <p class="text-3xl font-bold text-gray-900">{{ $groupSummary['total_students'] }}</p>
                <p class="text-sm text-gray-600 mt-2">{{ $recordType }}, active enrollments</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">With Records</p>
                <p class="text-3xl font-bold text-emerald-700">{{ $groupSummary['students_with_records'] }}</p>
                <p class="text-sm text-gray-600 mt-2">In the selected period</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Without Records</p>
                {{-- Neutral, never red: an unrecorded day is office work
                     outstanding, not an absence or a failure. --}}
                <p class="text-3xl font-bold text-gray-700">{{ $groupSummary['students_without_records'] }}</p>
                <p class="text-sm text-gray-600 mt-2">In the selected period</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Recorded Days</p>
                <p class="text-3xl font-bold text-gray-900">{{ $groupSummary['recorded_days'] }}</p>
                <p class="text-sm text-gray-600 mt-2">Total daily records</p>
            </div>
        </div>

        @if($groupSummary['students_by_label'] !== [])
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Students by Recorded Work</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach($groupSummary['students_by_label'] as $label => $count)
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-1">Students with {{ $label }}</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $count }}</p>
                        </div>
                    @endforeach
                </div>
                <p class="mt-4 text-sm text-gray-500">
                    Counts of students with at least one recorded day of that work. Not a measure of how much was done.
                </p>
            </div>
        @endif

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                {{-- Department -> Class -> Section narrow each other, the same
                     pattern the roster and academic pages use. --}}
                <form
                    method="GET"
                    action="{{ route('hifz.reports') }}"
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

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Program -->
                        <div>
                            <label for="record_type" class="block text-sm font-medium text-gray-700 mb-1">Program</label>
                            <select name="record_type" id="record_type"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @foreach($recordTypes as $type)
                                    <option value="{{ $type }}" {{ $recordType === $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                            {{-- The two programmes are reported separately on
                                 purpose: a Sabaq and a kitab lesson are not
                                 the same thing and must never be added into
                                 one figure. --}}
                            <p class="mt-1 text-sm text-gray-500">Hifz and Dars-e-Nizami are reported separately.</p>
                        </div>

                        <!-- Academic Session -->
                        <div>
                            <label for="academic_session_id" class="block text-sm font-medium text-gray-700 mb-1">Academic Session</label>
                            <select name="academic_session_id" id="academic_session_id"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Sessions</option>
                                @foreach($academicSessions as $session)
                                    <option value="{{ $session->id }}" {{ (int) $filters['academic_session_id'] === $session->id ? 'selected' : '' }}>
                                        {{ $session->name }}
                                    </option>
                                @endforeach
                            </select>
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

                        <!-- Class, narrowed by department -->
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

                        <!-- Section, narrowed by class -->
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
                            <p class="mt-1 text-sm text-gray-500" x-show="academicClassId && sections.length === 0" x-cloak>
                                This class has no sections.
                            </p>
                        </div>

                        <!-- Dates -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                                <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from'] }}"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                                <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to'] }}"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            Run Report
                        </button>
                        @if($hasFilters)
                            <a href="{{ route('hifz.reports', ['record_type' => $recordType]) }}"
                               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                Clear Filters
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Student-wise report -->
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">{{ $recordType }} — Student Progress</h3>
                @if($students->total() > 0)
                    <p class="text-sm text-gray-600 mt-1">
                        Showing {{ $students->firstItem() }} to {{ $students->lastItem() }} of {{ $students->total() }} students with records
                    </p>
                @endif
            </div>

            @if($students->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registration No.</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No.</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded Days</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Recorded</th>
                                @foreach($workFields as $field)
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Last {{ $fieldLabels[$field] ?? $field }}
                                    </th>
                                @endforeach
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            {{-- Class and Section come from the enrollment the
                                 latest record was written against, so a report
                                 over a past month names the placement the
                                 student held then. --}}
                            @foreach($students as $row)
                                @php
                                    $latest = $latestRecords->get($row->student_id);
                                    $enrollment = $latest?->studentAcademicEnrollment;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <a href="{{ route('students.show', $row->student_id) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                            {{ $row->full_name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $row->registration_number }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $row->roll_number ?: '—' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment?->academicClass?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment?->section?->name ?? 'No section' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            {{ $row->recorded_days }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $latest?->record_date?->format('d M, Y') ?? '—' }}
                                    </td>
                                    {{-- Printed verbatim. "1 page" stays
                                         "1 page". --}}
                                    @foreach($workFields as $field)
                                        <td class="px-4 py-4 text-sm text-gray-700 whitespace-nowrap">{{ $latest?->{$field} ?: '—' }}</td>
                                    @endforeach
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $latest?->teacher?->full_name ?? 'Not recorded' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('students.hifz.progress', $row->student_id) }}" class="text-blue-600 hover:text-blue-800">
                                            View Progress
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No {{ $recordType }} records found.</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        @if($hasFilters)
                            Try widening the date range or clearing the filters.
                        @else
                            Daily records are entered on the Hifz &amp; Quran roster, one class and one day at a time.
                        @endif
                    </p>
                </div>
            @endif
        </div>

        <!-- Students with nothing recorded -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Students Without Records</h3>
                <p class="text-sm text-gray-600 mt-1">
                    {{-- Driven by who is in the class now, because that is
                         who still has to be entered. --}}
                    Currently enrolled {{ $recordType }} students with no daily record in the selected period.
                </p>
            </div>

            @if($missing['count'] === 0)
                <div class="px-6 py-10 text-center">
                    <p class="text-sm font-medium text-gray-900">Every student in this group has a record for the period.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registration No.</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No.</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($missing['listed'] as $enrollment)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <a href="{{ route('students.show', $enrollment->student_id) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                            {{ $enrollment->student->full_name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->student->registration_number }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->student->roll_number ?: '—' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->academicClass?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->section?->name ?? 'No section' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">
                                            Not Recorded
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('hifz.index', array_filter([
                                                'record_date' => $filters['date_from'],
                                                'academic_session_id' => $enrollment->academic_session_id,
                                                'department_id' => $enrollment->department_id,
                                                'academic_class_id' => $enrollment->academic_class_id,
                                                'section_id' => $enrollment->section_id,
                                           ])) }}" class="text-blue-600 hover:text-blue-800">
                                            Open Roster
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($missing['truncated'])
                    <div class="px-6 py-4 border-t border-gray-200 text-sm text-gray-600">
                        Showing the first {{ $missing['listed']->count() }} of {{ $missing['count'] }}.
                        Narrow the class or section to see the rest.
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-layout.admin>
