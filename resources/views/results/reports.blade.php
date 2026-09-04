@php
    // Read only throughout. Results are recorded and corrected on the
    // Results page, which is the module's only writer.
    $activeFilters = array_filter(
        $filters,
        fn ($value, $key) => $value !== null && $value !== '' && $key !== 'term',
        ARRAY_FILTER_USE_BOTH
    );

    $percentageLabel = fn (?float $percentage) => $percentage === null
        ? 'N/A'
        : number_format($percentage, 2).'%';

    // What the Printable Report link carries: the same filters the page is
    // showing, minus the empties, so the PDF covers exactly these students.
    $pdfFilters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

    $statusBadgeClasses = fn (string $status) => match ($status) {
        'Passed' => 'bg-green-100 text-green-800',
        'Failed' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp

<x-layout.admin title="Result Reports">
    <x-slot name="header">
        Results — Result Reports
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('results.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Results
            </a>

            {{-- The same report as paper, carrying exactly the filters the
                 page is showing, in the chosen language, opened in a new
                 tab. --}}
            <span class="inline-flex items-center rounded-lg overflow-hidden bg-blue-600">
                <span class="inline-flex items-center px-3 py-2 text-white text-sm font-medium">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Printable Report
                </span>
                @foreach($reportLanguages as $code => $name)
                    <a href="{{ route('results.reports.pdf', $pdfFilters + ['language' => $code]) }}"
                       target="_blank" rel="noopener"
                       class="px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 border-l border-blue-500 transition-colors">
                        {{ $name }}
                    </a>
                @endforeach
            </span>
        </div>

        <!-- Intro -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-xl font-semibold text-gray-800">Madrassa Result Report — {{ $testType }}</h2>
            <p class="text-sm text-gray-600 mt-1">
                What has been recorded for the chosen term, and who has not been marked yet. Every student with
                an active Madrassa enrollment is listed, whether or not they have a result.
            </p>
            <p class="text-sm text-gray-600 mt-1">
                {{-- Said plainly: the two terms are never combined here. --}}
                First Term and Final Term are reported separately and are never averaged or added together.
            </p>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Madrassa Students</p>
                <p class="text-3xl font-bold text-gray-900">{{ $summary['students'] }}</p>
                <p class="text-sm text-gray-600 mt-2">In this report</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Results Entered</p>
                <p class="text-3xl font-bold text-gray-900">{{ $summary['entered'] }}</p>
                <p class="text-sm text-gray-600 mt-2">
                    {{ count($summary['terms']) === 1 ? $summary['terms'][0] : 'Both terms' }}
                </p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Not Entered</p>
                <p class="text-3xl font-bold text-gray-900">{{ $summary['not_entered'] }}</p>
                {{-- Neutral, never red. An unmarked paper is office work
                     outstanding, not a failure. --}}
                <p class="text-sm text-gray-600 mt-2">Still to be recorded</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Passed</p>
                <p class="text-3xl font-bold text-green-700">{{ $summary['passed'] }}</p>
                <p class="text-sm text-gray-600 mt-2">Of the results entered</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Failed</p>
                <p class="text-3xl font-bold text-red-700">{{ $summary['failed'] }}</p>
                <p class="text-sm text-gray-600 mt-2">Of the results entered</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Average Percentage</p>
                <p class="text-3xl font-bold text-gray-900">{{ $percentageLabel($summary['average_percentage']) }}</p>
                {{-- Total obtained over total possible, exactly as a mark
                     sheet is totalled. Not the mean of the students' own
                     percentages, which would weigh a paper out of 50 the
                     same as a paper out of 500. --}}
                <p class="text-sm text-gray-600 mt-2">Total obtained &divide; total marks</p>
            </div>
        </div>

        <!-- Grade breakdown -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Grade Breakdown</h3>
                <p class="text-sm text-gray-600 mt-1">
                    Counted from the stored grades, over the whole report rather than this page.
                </p>
            </div>

            <div class="px-6 py-4">
                <div class="flex flex-wrap gap-3">
                    @foreach($gradeBreakdown as $grade => $count)
                        <div class="border border-gray-200 rounded-lg px-4 py-3 min-w-[7rem]">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $grade }}</p>
                            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $count }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                {{-- Department -> Class -> Section narrow each other, the same
                     pattern the Results, academic and attendance pages use.
                     There is no track filter: the report is Madrassa-only by
                     construction. --}}
                <form
                    method="GET"
                    action="{{ route('results.reports') }}"
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
                        <!-- Term -->
                        <div>
                            <label for="term" class="block text-sm font-medium text-gray-700 mb-1">Term</label>
                            <select name="term" id="term"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @foreach($termOptions as $option)
                                    <option value="{{ $option }}" {{ $filters['term'] === $option ? 'selected' : '' }}>{{ $option }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">All Terms lists each term on its own row.</p>
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
                            <p class="mt-1 text-sm text-gray-500">Defaults to the current session.</p>
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
                            Apply Filters
                        </button>
                        @if($activeFilters !== [])
                            <a href="{{ route('results.reports', ['term' => $filters['term']]) }}"
                               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                Clear Filters
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- The report -->
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    Student Results — {{ $filters['term'] }}
                </h3>
                @if($enrollments->total() > 0)
                    <p class="text-sm text-gray-600 mt-1">
                        Showing {{ $enrollments->firstItem() }} to {{ $enrollments->lastItem() }} of {{ $enrollments->total() }} Madrassa students
                    </p>
                @endif
            </div>

            @if(count($rows) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registration No.</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No.</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Session</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Programme</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Term</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Test</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Marks</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obtained Marks</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Result Date</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            {{-- The session, department, class and section come
                                 from the enrollment each result was recorded
                                 against, never from the student's current
                                 placement, so a row from before a promotion
                                 still names the class the test was sat in.

                                 With All Terms selected a student has one row
                                 per term. The two are shown side by side and
                                 are never combined. --}}
                            @foreach($rows as $row)
                                @php
                                    $enrollment = $row['enrollment'];
                                    $student = $row['student'];
                                    $result = $row['result'];
                                @endphp
                                <tr class="hover:bg-gray-50 {{ $row['first_of_group'] ? 'border-t-2 border-gray-200' : '' }}">
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        {{-- The existing Chunk 2 history page.
                                             No second history system. --}}
                                        <a href="{{ route('students.results', $enrollment->student_id) }}"
                                           class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                            {{ $student?->full_name ?? 'Unknown student' }}
                                        </a>
                                        {{-- Two different documents, named so
                                             they are not confused: the short
                                             result PDF in either language,
                                             and the long detailed track
                                             record. --}}
                                        <span class="block text-xs text-gray-500">
                                            Result PDF:
                                            @foreach($reportLanguages as $code => $name)
                                                <a href="{{ route('students.results.short-pdf', [
                                                        'student' => $enrollment->student_id,
                                                        'term' => $row['term'],
                                                        'language' => $code,
                                                   ]) }}"
                                                   target="_blank" rel="noopener"
                                                   class="hover:text-gray-700 underline">{{ $name }}</a>@if(! $loop->last) | @endif
                                            @endforeach
                                        </span>
                                        <a href="{{ route('students.results.pdf', $enrollment->student_id) }}"
                                           target="_blank" rel="noopener"
                                           class="block text-xs text-gray-500 hover:text-gray-700">
                                            Detailed report (PDF)
                                        </a>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student?->registration_number ?? 'N/A' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student?->roll_number ?: '—' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->academicSession?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->department?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->academicClass?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->section?->name ?? 'No section' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student?->student_type ?? 'N/A' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $row['term'] }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $testType }}</td>

                                    @if($result)
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format((float) $result->total_marks, 2) }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format((float) $result->obtained_marks, 2) }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $result->formattedPercentage() }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm">
                                            {{-- Links to the existing result
                                                 detail page. --}}
                                            <a href="{{ route('results.show', $result->id) }}"
                                               class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $result->gradeBadgeClasses() }}">
                                                {{ $result->grade }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $result->result_date?->format('d M, Y') ?? '—' }}</td>
                                    @else
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">-</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">-</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">-</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">-</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">-</td>
                                    @endif

                                    <td class="px-4 py-4 whitespace-nowrap text-sm">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $statusBadgeClasses($row['status']) }}">
                                            {{ $row['status'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{-- The filters are carried onto every page link, so
                         paging through a filtered report stays filtered. --}}
                    {{ $enrollments->links() }}
                </div>
            @else
                <div class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No Madrassa students match these filters.</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Only students with a Madrassa enrollment are reported here. School-only students are not
                        part of this module.
                    </p>
                    @if($activeFilters !== [])
                        <div class="mt-6">
                            <a href="{{ route('results.reports', ['term' => $filters['term']]) }}"
                               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Clear all filters
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layout.admin>
