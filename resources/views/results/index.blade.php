@php
    // Built once: every "open a result" link carries the filters back so
    // saving returns to the list the student was picked from.
    $carriedFilters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

    // The term is always in effect, so it is not what makes the page
    // "filtered". Clearing should return to the default list, not to a
    // page with no term at all.
    $hasFilters = array_diff_key($carriedFilters, ['term' => null]) !== [];
@endphp

<x-layout.admin title="Results">
    <x-slot name="header">
        Results
    </x-slot>

    <div class="space-y-6">
        <!-- Intro -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Madrassa Results — Grand Test</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        The madrassa runs two terms, First and Final, and each one has a Grand Test. Choose a term
                        below, narrow to a class if you like, and record each student's marks.
                    </p>
                    <p class="text-sm text-gray-600 mt-1">
                        Only students with an active Madrassa enrollment appear here. A Hifz + School student appears
                        once, through their Madrassa enrollment; a School-only student is not part of this module.
                    </p>
                </div>

                <div class="flex flex-wrap items-start gap-2 flex-shrink-0">
                    {{-- The report over what this page has recorded. Read
                         only, and it also names who has not been marked
                         yet. --}}
                    <a href="{{ route('results.reports') }}"
                       class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Reports
                    </a>

                    {{-- The PDF, carrying whatever the page is filtered to
                         and the language chosen here. target="_blank" so it
                         opens in a tab rather than replacing the page being
                         worked on. --}}
                    <span class="inline-flex items-center rounded-lg border border-gray-300 bg-white overflow-hidden">
                        <span class="inline-flex items-center px-3 py-2 text-sm text-gray-500 bg-gray-50">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            Printable Report
                        </span>
                        @foreach($reportLanguages as $code => $name)
                            <a href="{{ route('results.reports.pdf', $carriedFilters + ['language' => $code]) }}"
                               target="_blank" rel="noopener"
                               class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 border-l border-gray-300 transition-colors">
                                {{ $name }}
                            </a>
                        @endforeach
                    </span>
                </div>
            </div>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Results Recorded</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $summary['total'] }}</p>
                        <p class="text-sm text-gray-600 mt-2">All terms</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">First Term</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $summary['first_term'] }}</p>
                        <p class="text-sm text-gray-600 mt-2">Grand Test results</p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Final Term</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $summary['final_term'] }}</p>
                        <p class="text-sm text-gray-600 mt-2">Grand Test results</p>
                    </div>
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Madrassa Students</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $summary['madrassa_students'] }}</p>
                        <p class="text-sm text-gray-600 mt-2">Active enrollments</p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        @if($filteredStudent)
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-center justify-between">
                <p class="text-sm text-blue-800">
                    Showing the results of
                    <a href="{{ route('students.show', $filteredStudent->id) }}" class="font-semibold underline">{{ $filteredStudent->full_name }}</a>
                    ({{ $filteredStudent->registration_number }}).
                </p>
                <a href="{{ route('results.index') }}" class="text-sm font-medium text-blue-700 hover:text-blue-900">Show all students</a>
            </div>
        @endif

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                {{-- Department -> Class -> Section narrow each other, the same
                     pattern the academic, attendance and Hifz pages use. --}}
                <form
                    method="GET"
                    action="{{ route('results.index') }}"
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
                    @if($filters['student_id'])
                        {{-- Kept across a search so narrowing to one student
                             stays narrowed to that student. --}}
                        <input type="hidden" name="student_id" value="{{ $filters['student_id'] }}">
                    @endif

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
                        <!-- Term -->
                        <div>
                            <label for="term" class="block text-sm font-medium text-gray-700 mb-1">Term</label>
                            <select name="term" id="term"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @foreach($terms as $option)
                                    <option value="{{ $option }}" {{ $term === $option ? 'selected' : '' }}>{{ $option }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Decides which {{ $testType }} result each row shows.</p>
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
                    </div>

                    <div class="flex items-center space-x-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Load Results
                        </button>
                        @if($hasFilters)
                            <a href="{{ route('results.index', ['term' => $term]) }}"
                               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                Clear Filters
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- The students and their result for the chosen term -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">{{ $term }} — {{ $testType }}</h3>
                @if($enrollments->total() > 0)
                    <p class="text-sm text-gray-600 mt-1">
                        Showing {{ $enrollments->firstItem() }} to {{ $enrollments->lastItem() }} of {{ $enrollments->total() }} Madrassa students
                    </p>
                @endif
            </div>

            @if($enrollments->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registration No.</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No.</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Program</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $testType }} Result</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Marks</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obtained Marks</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            {{-- One row per student: a Hifz + School student
                                 appears once, through their madrassa
                                 enrollment. --}}
                            @foreach($enrollments as $enrollment)
                                @php
                                    $result = $enrollment->resultForTerm;
                                    $student = $enrollment->student;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <a href="{{ route('students.show', $enrollment->student_id) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                            {{ $student->full_name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student->registration_number }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student->roll_number ?: '—' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->academicClass?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->section?->name ?? 'No section' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student->student_type }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm">
                                        {{-- Green for recorded, neutral grey for
                                             not. Never red: a term nobody has
                                             marked yet is office work
                                             outstanding, not a failure. --}}
                                        @if($result)
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                Recorded
                                            </span>
                                        @else
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">
                                                Not Entered
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $result ? number_format((float) $result->total_marks, 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $result ? number_format((float) $result->obtained_marks, 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $result?->formattedPercentage() ?? '—' }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm">
                                        @if($result)
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $result->gradeBadgeClasses() }}">
                                                {{ $result->grade }}
                                            </span>
                                        @else
                                            <span class="text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                        {{-- A term that already has a result is
                                             only ever viewed or corrected. Add
                                             is not offered, so a duplicate is
                                             never attempted. --}}
                                        {{-- History is offered on every row,
                                             marked or not: a student with no
                                             result this term may still have
                                             one from a previous placement. --}}
                                        <div class="flex items-center gap-2">
                                            @if($result)
                                                <a href="{{ route('results.show', $result->id) }}"
                                                   class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                                    View
                                                </a>
                                                <a href="{{ route('results.edit', ['result' => $result->id, 'filters' => $carriedFilters]) }}"
                                                   class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                                    Edit
                                                </a>
                                            @else
                                                <a href="{{ route('results.create', [
                                                        'student_academic_enrollment_id' => $enrollment->id,
                                                        'term' => $term,
                                                        'filters' => $carriedFilters,
                                                   ]) }}"
                                                   class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                                    Add Result
                                                </a>
                                            @endif

                                            <a href="{{ route('students.results', $enrollment->student_id) }}"
                                               class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                                History
                                            </a>

                                            {{-- The short, result-focused PDF
                                                 for this student, carrying
                                                 the term the page is showing
                                                 and the language chosen here.
                                                 Not the long detailed track
                                                 record: that is a separate
                                                 action on the student's own
                                                 pages. --}}
                                            <span class="inline-flex items-center rounded-lg border border-gray-300 overflow-hidden">
                                                <span class="px-2 py-1.5 text-xs text-gray-500 bg-gray-50">PDF</span>
                                                @foreach($reportLanguages as $code => $name)
                                                    <a href="{{ route('students.results.short-pdf', [
                                                            'student' => $enrollment->student_id,
                                                            'term' => $term,
                                                            'language' => $code,
                                                       ]) }}"
                                                       target="_blank" rel="noopener"
                                                       class="px-2 py-1.5 text-gray-700 hover:bg-gray-50 border-l border-gray-300 transition-colors">
                                                        {{ $name }}
                                                    </a>
                                                @endforeach
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $enrollments->links() }}
                </div>
            @else
                <div class="px-6 py-10 text-center">
                    <p class="text-sm font-medium text-gray-900">No Madrassa students match these filters.</p>
                    <p class="mt-1 text-sm text-gray-500">
                        Only students with an active Madrassa enrollment appear here. School-only students are
                        not part of this module.
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-layout.admin>
