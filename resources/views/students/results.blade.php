@php
    // Read only throughout. Results are recorded and corrected on the
    // Results page, which is the module's only writer.
    $currentFilters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
@endphp

<x-layout.admin title="Result History">
    <x-slot name="header">
        Results — Result History
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-4">
            <a href="{{ route('students.show', $student->id) }}#results" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Student Profile
            </a>

            <a href="{{ route('results.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Results
            </a>
        </div>

            @if($currentEnrollment)
                {{-- The short result PDF, in either language. Separate from
                     the detailed track record beside it. --}}
                <span class="inline-flex items-center rounded-lg border border-gray-300 bg-white overflow-hidden">
                    <span class="px-3 py-2 text-sm text-gray-500 bg-gray-50">Result PDF</span>
                    @foreach($reportLanguages as $code => $name)
                        <a href="{{ route('students.results.short-pdf', ['student' => $student->id, 'language' => $code]) }}"
                           target="_blank" rel="noopener"
                           class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 border-l border-gray-300 transition-colors">
                            {{ $name }}
                        </a>
                    @endforeach
                </span>

                {{-- This student's whole Madrassa track record as paper.
                     Only offered when there is a Madrassa placement: the PDF
                     route 404s without one. --}}
                <a href="{{ route('students.results.pdf', $student->id) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Student Detailed Report
                </a>
            @endif
        </div>

        <!-- Who this is -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="h-16 w-16 rounded-full bg-gray-300 flex items-center justify-center overflow-hidden flex-shrink-0">
                        @if($student->photo && \Storage::disk('public')->exists($student->photo))
                            <img src="{{ asset('storage/' . $student->photo) }}" class="h-16 w-16 rounded-full object-cover" alt="{{ $student->full_name }}">
                        @else
                            <span class="text-xl font-medium text-gray-700">{{ mb_strtoupper(mb_substr($student->full_name, 0, 1)) }}</span>
                        @endif
                    </div>

                    <div>
                        <h2 class="text-xl font-semibold text-gray-800">{{ $student->full_name }}</h2>
                        <p class="text-sm text-gray-600 mt-1">
                            {{ $student->registration_number }}
                            @if($student->roll_number) &middot; Roll No. {{ $student->roll_number }} @endif
                        </p>
                    </div>
                </div>

                @if($currentEnrollment)
                    <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-emerald-100 text-emerald-800">
                        {{ $student->student_type }}
                    </span>
                @endif
            </div>

            {{-- The student's placement as it stands today. Every result in
                 the table below reads its own placement from its own
                 enrollment instead, so an old result keeps the class it was
                 recorded in. --}}
            @if($currentEnrollment)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6 border-t border-gray-200 pt-4">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Current Session</label>
                        <p class="text-gray-900">{{ $currentEnrollment->academicSession?->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Current Department</label>
                        <p class="text-gray-900">{{ $currentEnrollment->department?->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Current Class</label>
                        <p class="text-gray-900">{{ $currentEnrollment->academicClass?->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Current Section</label>
                        <p class="text-gray-900">{{ $currentEnrollment->section?->name ?? 'No section' }}</p>
                    </div>
                </div>

                <p class="mt-4 text-sm text-gray-500">
                    Madrassa results only. This is the student's placement today; each result below shows the
                    placement it was actually recorded under.
                </p>
            @endif
        </div>

        @if($currentEnrollment === null)
            {{-- No madrassa enrollment has ever existed for this student, so
                 there is nothing for this page to show and nothing to
                 filter. A school-only student lands here, and the school
                 side of the system is not something this page reads. --}}
            <div class="bg-white rounded-lg shadow-sm px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">This student has no Madrassa results.</h3>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $student->full_name }} has no Madrassa enrollment, and results are only recorded against
                    a Madrassa enrollment. School records are kept in their own modules.
                </p>
                <div class="mt-6">
                    <a href="{{ route('students.show', $student->id) }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Back to Student Profile
                    </a>
                </div>
            </div>
        @else
            <!-- Summary cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-600 mb-1">First Term</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $summary['first_term'] }}</p>
                    <p class="text-sm text-gray-600 mt-2">{{ $testType }} results</p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-600 mb-1">Final Term</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $summary['final_term'] }}</p>
                    <p class="text-sm text-gray-600 mt-2">{{ $testType }} results</p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-600 mb-1">Results Recorded</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $summary['total'] }}</p>
                    <p class="text-sm text-gray-600 mt-2">{{ $hasFilters ? 'Matching these filters' : 'All Madrassa results' }}</p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-600 mb-1">Latest Result</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $summary['latest_date']?->format('d M, Y') ?? 'None' }}</p>
                    {{-- More than one placement means the student has been
                         promoted. The old results keep the class they were
                         recorded in. --}}
                    <p class="text-sm text-gray-600 mt-2">
                        {{ $summary['placements'] }} Madrassa {{ $summary['placements'] === 1 ? 'placement' : 'placements' }}
                    </p>
                </div>
            </div>

            <!-- Term summary -->
            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Term Summary</h3>
                    {{-- Each term stands on its own. Nothing here averages
                         the two or adds their marks together: a First Term
                         and a Final Term are separate papers. --}}
                    <p class="text-sm text-gray-600 mt-1">
                        Each term is shown on its own. The two are never averaged or added together.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Term</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Marks</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obtained Marks</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded Under</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($termSummary as $termName => $termResult)
                                @php($termEnrollment = $termResult?->studentAcademicEnrollment)
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $termName }} {{ $testType }}
                                    </td>
                                    @if($termResult)
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format((float) $termResult->total_marks, 2) }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format((float) $termResult->obtained_marks, 2) }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $termResult->formattedPercentage() }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $termResult->gradeBadgeClasses() }}">
                                                {{ $termResult->grade }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                                            {{ $termEnrollment?->academicSession?->name ?? 'N/A' }}
                                            &middot; {{ $termEnrollment?->academicClass?->name ?? 'N/A' }}
                                            @if($termEnrollment?->section) / {{ $termEnrollment->section->name }} @endif
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="{{ route('results.show', $termResult->id) }}" class="text-blue-600 hover:text-blue-800">View Result</a>
                                        </td>
                                    @else
                                        <td class="px-4 py-4 whitespace-nowrap text-sm" colspan="6">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">
                                                Not Entered
                                            </span>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm">
                <!-- Filters -->
                <div class="px-6 py-4 border-b border-gray-200">
                    {{-- No search box and no student or enrollment field: the
                         student is already known, which is the whole premise
                         of this page. These four only narrow, and they
                         combine with AND. --}}
                    <form method="GET" action="{{ route('students.results', $student->id) }}" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label for="term" class="block text-sm font-medium text-gray-700 mb-1">Term</label>
                                <select name="term" id="term"
                                    class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">All Terms</option>
                                    @foreach($terms as $option)
                                        <option value="{{ $option }}" {{ $filters['term'] === $option ? 'selected' : '' }}>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="academic_session_id" class="block text-sm font-medium text-gray-700 mb-1">Academic Session</label>
                                <select name="academic_session_id" id="academic_session_id"
                                    class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">All Sessions</option>
                                    {{-- Only the sessions this student was
                                         actually enrolled in on the madrassa
                                         side. --}}
                                    @foreach($academicSessions as $session)
                                        <option value="{{ $session->id }}" {{ (int) $filters['academic_session_id'] === $session->id ? 'selected' : '' }}>
                                            {{ $session->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                                <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from'] }}"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                                <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to'] }}"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <div class="flex items-center space-x-2">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Apply Filters
                            </button>
                            @if($currentFilters !== [])
                                <a href="{{ route('students.results', $student->id) }}"
                                   class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                    Clear Filters
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                <!-- Result history -->
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Result History</h3>
                    @if($results->total() > 0)
                        <p class="text-sm text-gray-600 mt-1">
                            Showing {{ $results->firstItem() }} to {{ $results->lastItem() }} of {{ $results->total() }} results
                        </p>
                    @endif
                </div>

                @if($results->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Term</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Test</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Session</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Marks</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obtained Marks</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                {{-- Newest first. The session, department,
                                     class and section come from the enrollment
                                     each result was recorded against, never
                                     from the student's current placement, so a
                                     row from before a promotion still names
                                     the class the test was sat in. --}}
                                @foreach($results as $result)
                                    @php($enrollment = $result->studentAcademicEnrollment)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $result->result_date?->format('d M, Y') ?? '—' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $result->term }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $result->test_type }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment?->academicSession?->name ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment?->department?->name ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment?->academicClass?->name ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment?->section?->name ?? 'No section' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format((float) $result->total_marks, 2) }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format((float) $result->obtained_marks, 2) }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $result->formattedPercentage() }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $result->gradeBadgeClasses() }}">
                                                {{ $result->grade }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-700 max-w-xs">{{ $result->remarks ?: '—' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="{{ route('results.show', $result->id) }}" class="text-blue-600 hover:text-blue-800">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200">
                        {{-- The filters are carried onto every page link, so
                             paging through a filtered history stays
                             filtered. --}}
                        {{ $results->links() }}
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No results found.</h3>
                        @if($hasFilters)
                            <p class="mt-1 text-sm text-gray-500">Try widening the date range or clearing the filters.</p>
                            <div class="mt-6">
                                <a href="{{ route('students.results', $student->id) }}"
                                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Clear all filters
                                </a>
                            </div>
                        @else
                            <p class="mt-1 text-sm text-gray-500">
                                This student has no Madrassa results yet. Results are recorded on the Results page,
                                one term at a time.
                            </p>
                            <div class="mt-6">
                                <a href="{{ route('results.index') }}"
                                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                                    Go to Results
                                </a>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-layout.admin>
