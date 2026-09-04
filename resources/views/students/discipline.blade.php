@php
    // Read only throughout. Records are written and corrected in the
    // Discipline module, which is this module's only writer.
    $currentFilters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
@endphp

<x-layout.admin title="Discipline History">
    <x-slot name="header">
        Discipline — Discipline History
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4">
                <a href="{{ route('students.show', $student->id) }}#discipline" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Student Profile
                </a>

                <a href="{{ route('discipline.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Discipline
                </a>
            </div>

            <a href="{{ route('discipline.create', ['student_id' => $student->id]) }}"
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Record
            </a>
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

                {{-- Derived from the student's whole history, not from the
                     filtered set: hiding the High incident behind a filter
                     must not turn a Serious Concern into Good. --}}
                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ \App\Models\DisciplineRecord::badgeClassesForStatus($status) }}">
                    {{ $status }}
                </span>
            </div>

            {{-- The student's placement as it stands today. Nothing below
                 claims an incident happened in this class: a discipline
                 record is attached to the student and never carried a
                 placement in the first place. --}}
            @if($currentEnrollment)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6 border-t border-gray-200 pt-4">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Current Session</label>
                        <p class="text-gray-900">{{ $currentEnrollment->academicSession?->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Current Class</label>
                        <p class="text-gray-900">{{ $currentEnrollment->academicClass?->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Current Section</label>
                        <p class="text-gray-900">{{ $currentEnrollment->section?->name ?? 'No section' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Current Programme</label>
                        <p class="text-gray-900">{{ $student->student_type ?? 'N/A' }}</p>
                    </div>
                </div>

                <p class="mt-4 text-sm text-gray-500">
                    This is the student's placement today. Discipline records belong to the student, so an incident
                    below did not necessarily happen in this class or session.
                </p>
            @endif
        </div>

        <!-- Summary cards -->
        {{-- Counted in SQL over the filtered set. The "Total Incidents"
             card says which set it is counting so the numbers are never
             mistaken for the student's whole record. --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Total Incidents</p>
                <p class="text-3xl font-bold text-gray-900">{{ $summary['total'] }}</p>
                <p class="text-sm text-gray-600 mt-2">{{ $hasFilters ? 'Matching these filters' : 'All incidents' }}</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Low</p>
                <p class="text-3xl font-bold text-gray-900">{{ $summary['low'] }}</p>
                <p class="text-sm text-gray-600 mt-2">Low severity</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Medium</p>
                <p class="text-3xl font-bold text-gray-900">{{ $summary['medium'] }}</p>
                <p class="text-sm text-gray-600 mt-2">Medium severity</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">High</p>
                <p class="text-3xl font-bold text-gray-900">{{ $summary['high'] }}</p>
                <p class="text-sm text-gray-600 mt-2">High severity</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Latest Incident</p>
                <p class="text-3xl font-bold text-gray-900">{{ $summary['latest_date']?->format('d M, Y') ?? 'None' }}</p>
                <p class="text-sm text-gray-600 mt-2">Most recent on file</p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm">
            <!-- Filters -->
            <div class="px-6 py-4 border-b border-gray-200">
                {{-- No student field: the student is already known, which is
                     the whole premise of this page. These four only narrow,
                     and they combine with AND. --}}
                <form method="GET" action="{{ route('students.discipline', $student->id) }}" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select name="category" id="category"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Categories</option>
                                @foreach($categories as $option)
                                    <option value="{{ $option }}" {{ $filters['category'] === $option ? 'selected' : '' }}>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="severity" class="block text-sm font-medium text-gray-700 mb-1">Severity</label>
                            <select name="severity" id="severity"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Severities</option>
                                @foreach($severities as $option)
                                    <option value="{{ $option }}" {{ $filters['severity'] === $option ? 'selected' : '' }}>{{ $option }}</option>
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
                            <a href="{{ route('students.discipline', $student->id) }}"
                               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                Clear Filters
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Discipline history -->
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Discipline History</h3>
                @if($records->total() > 0)
                    <p class="text-sm text-gray-600 mt-1">
                        Showing {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }} incidents
                    </p>
                @endif
            </div>

            @if($records->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Severity</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action Taken</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            {{-- Newest first, and every row is this student's:
                                 the query starts from the route's student id
                                 and the filters can only narrow it. --}}
                            @foreach($records as $record)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $record->date?->format('d M, Y') ?? '—' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $record->category }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $record->severityBadgeClasses() }}">
                                            {{ $record->severity }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-700 max-w-xs">{{ $record->description }}</td>
                                    <td class="px-4 py-4 text-sm text-gray-700">{{ $record->action_taken ?: '—' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">{{ $record->recorder?->name ?? 'Not recorded' }}</td>
                                    <td class="px-4 py-4 text-sm text-gray-700 max-w-xs">{{ $record->remarks ?: '—' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('discipline.show', $record->id) }}" class="text-blue-600 hover:text-blue-800">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{-- The filters are carried onto every page link, so
                         paging through a filtered history stays filtered. --}}
                    {{ $records->links() }}
                </div>
            @else
                <div class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No discipline records found.</h3>
                    @if($hasFilters)
                        <p class="mt-1 text-sm text-gray-500">
                            @if($overallTotal > 0)
                                This student has {{ $overallTotal }} {{ $overallTotal === 1 ? 'incident' : 'incidents' }} on file, none matching these filters.
                            @else
                                Try widening the date range or clearing the filters.
                            @endif
                        </p>
                        <div class="mt-6">
                            <a href="{{ route('students.discipline', $student->id) }}"
                               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Clear all filters
                            </a>
                        </div>
                    @else
                        <p class="mt-1 text-sm text-gray-500">
                            {{ $student->full_name }} has a clean discipline record.
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layout.admin>
