@php
    // Built once: every "open a record" link carries the filters back so
    // saving returns to the list the record was opened from.
    $carriedFilters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
@endphp

<x-layout.admin title="Discipline">
    <x-slot name="header">
        Discipline
    </x-slot>

    <div class="space-y-6">
        <!-- Intro -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Discipline Records</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        One incident per record: what happened, how serious it was, and what the office did about it.
                    </p>
                    <p class="text-sm text-gray-600 mt-1">
                        A record belongs to the student, not to a class or a session, so it stays on file after the
                        student is promoted or moved to another section.
                    </p>
                </div>

                <div class="flex-shrink-0">
                    <a href="{{ route('discipline.create', $carriedFilters) }}"
                       class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Record
                    </a>
                </div>
            </div>
        </div>

        <!-- Summary cards -->
        {{-- Counted in SQL over the filtered set, so they describe the table
             below rather than the whole table, and they do not get more
             expensive as records accumulate. --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Total Incidents</p>
                <p class="text-3xl font-bold text-gray-900">{{ $summary['total'] }}</p>
                <p class="text-sm text-gray-600 mt-2">{{ $hasFilters ? 'Matching these filters' : 'All records' }}</p>
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
        </div>

        <div class="bg-white rounded-lg shadow-sm">
            <!-- Filters -->
            <div class="px-6 py-4 border-b border-gray-200">
                {{-- Five filters, all of them narrowing and all of them
                     combining with AND: a High severity in the Uniform
                     category means both, never either. --}}
                <form method="GET" action="{{ route('discipline.index') }}" class="space-y-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Student</label>
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
                        @if($hasFilters)
                            <a href="{{ route('discipline.index') }}"
                               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                Clear Filters
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Records -->
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Records</h3>
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
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Severity</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action Taken</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            {{-- Newest incident first. The student and the
                                 recorder are eager loaded, so the whole page
                                 costs the same handful of queries however
                                 many rows it holds. --}}
                            @foreach($records as $record)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4 whitespace-nowrap text-sm">
                                        <a href="{{ route('students.show', $record->student_id) }}" class="font-medium text-blue-600 hover:text-blue-800">
                                            {{ $record->student?->full_name ?? 'Unknown student' }}
                                        </a>
                                        <p class="text-gray-500">{{ $record->student?->registration_number ?? 'N/A' }}</p>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $record->date?->format('d M, Y') ?? '—' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $record->category }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $record->severityBadgeClasses() }}">
                                            {{ $record->severity }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-700 max-w-xs">{{ $record->action_taken ?: '—' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">{{ $record->recorder?->name ?? 'Not recorded' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium space-x-3">
                                        <a href="{{ route('discipline.show', $record->id) }}" class="text-blue-600 hover:text-blue-800">View</a>
                                        <a href="{{ route('discipline.edit', $record->id) }}" class="text-gray-600 hover:text-gray-900">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{-- The filters are carried onto every page link, so
                         paging through a filtered list stays filtered. --}}
                    {{ $records->links() }}
                </div>
            @else
                <div class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No discipline records found.</h3>
                    @if($hasFilters)
                        <p class="mt-1 text-sm text-gray-500">Try widening the date range or clearing the filters.</p>
                        <div class="mt-6">
                            <a href="{{ route('discipline.index') }}"
                               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Clear all filters
                            </a>
                        </div>
                    @else
                        <p class="mt-1 text-sm text-gray-500">Nothing has been recorded yet.</p>
                        <div class="mt-6">
                            <a href="{{ route('discipline.create') }}"
                               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                                Add Record
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layout.admin>
