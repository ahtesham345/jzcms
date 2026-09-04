@php
    // Read only throughout. Entry and corrections happen on the Hifz &
    // Quran roster, which is the module's only writer.
    $currentFilters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
@endphp

<x-layout.admin title="Daily Academic Records">
    <x-slot name="header">
        Hifz &amp; Quran — Daily Academic Records
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4">
                <a href="{{ route('students.show', $student->id) }}#hifz-quran" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Student Profile
                </a>

                <a href="{{ route('hifz.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Hifz &amp; Quran
                </a>
            </div>
        </div>

        <!-- Who this is -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
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
                            &middot; {{ $student->student_type }}
                        </p>
                    </div>
                </div>

                @if($summary['record_type'])
                    <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-emerald-100 text-emerald-800">
                        {{ $summary['record_type'] }}
                    </span>
                @endif
            </div>

            {{-- Madrassa only, spelled out. A Hifz + School student's school
                 enrollment is not a row this page can reach. --}}
            <p class="mt-4 text-sm text-gray-500">
                Madrassa daily academic records only. Attendance and school records are kept in their own modules.
            </p>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Records Shown</p>
                <p class="text-3xl font-bold text-gray-900">{{ $summary['total'] }}</p>
                <p class="text-sm text-gray-600 mt-2">{{ $hasFilters ? 'Matching these filters' : 'All daily records' }}</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Latest Record</p>
                <p class="text-3xl font-bold text-gray-900">{{ $summary['last_date']?->format('d M, Y') ?? 'None' }}</p>
                <p class="text-sm text-gray-600 mt-2">
                    @if($summary['first_date'])
                        Earliest {{ $summary['first_date']->format('d M, Y') }}
                    @else
                        Nothing recorded yet
                    @endif
                </p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Madrassa Placements</p>
                <p class="text-3xl font-bold text-gray-900">{{ $summary['placements'] }}</p>
                {{-- More than one means the student has been promoted. The
                     old records keep the class they were made in. --}}
                <p class="text-sm text-gray-600 mt-2">Including past placements</p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm">
            <!-- Filters -->
            <div class="px-6 py-4 border-b border-gray-200">
                {{-- No search box: the student is already known, which is the
                     whole premise of this page. --}}
                <form method="GET" action="{{ route('students.hifz', $student->id) }}" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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

                        <div>
                            <label for="academic_session_id" class="block text-sm font-medium text-gray-700 mb-1">Academic Session</label>
                            <select name="academic_session_id" id="academic_session_id"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Sessions</option>
                                {{-- Only the sessions this student was actually
                                     enrolled in on the madrassa side. --}}
                                @foreach($academicSessions as $session)
                                    <option value="{{ $session->id }}" {{ (int) $filters['academic_session_id'] === $session->id ? 'selected' : '' }}>
                                        {{ $session->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="record_type" class="block text-sm font-medium text-gray-700 mb-1">Program</label>
                            <select name="record_type" id="record_type"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Programs</option>
                                @foreach($recordTypes as $type)
                                    <option value="{{ $type }}" {{ $filters['record_type'] === $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Apply Filters
                        </button>
                        @if($currentFilters !== [])
                            <a href="{{ route('students.hifz', $student->id) }}"
                               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                Clear Filters
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Records -->
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Daily Records</h3>
                @if($records->total() > 0)
                    <p class="text-sm text-gray-600 mt-1">
                        Showing {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }} daily records
                    </p>
                @endif
            </div>

            @if($records->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Program</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class / Section</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Work</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            {{-- Newest first. The class and section come from
                                 the enrollment each record was written
                                 against, so a row from before a promotion
                                 still names the class it was made in. --}}
                            @foreach($records as $record)
                                @php($enrollment = $record->studentAcademicEnrollment)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $record->record_date->format('d M, Y') }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $record->recordTypeBadgeClasses() }}">
                                            {{ $record->record_type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $enrollment?->academicClass?->name ?? 'N/A' }}
                                        @if($enrollment?->section) / {{ $enrollment->section->name }} @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $record->teacher?->full_name ?? 'Not recorded' }}</td>
                                    <td class="px-4 py-4 text-sm text-gray-700 max-w-md">
                                        @forelse($record->workHighlights() as $label => $value)
                                            <div><span class="font-medium text-gray-900">{{ $label }}:</span> {{ $value }}</div>
                                        @empty
                                            <span class="text-gray-400">No work recorded</span>
                                        @endforelse
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-700 max-w-xs">{{ $record->remarks ?: '—' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('hifz.show', $record->id) }}" class="text-blue-600 hover:text-blue-800">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $records->links() }}
                </div>
            @else
                <div class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No daily records found.</h3>
                    @if($hasFilters)
                        <p class="mt-1 text-sm text-gray-500">Try widening the date range or clearing the filters.</p>
                        <div class="mt-6">
                            <a href="{{ route('students.hifz', $student->id) }}"
                               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Clear all filters
                            </a>
                        </div>
                    @else
                        <p class="mt-1 text-sm text-gray-500">
                            Daily records are entered on the Hifz &amp; Quran roster, one class and one day at a time.
                        </p>
                        <div class="mt-6">
                            <a href="{{ route('hifz.index') }}"
                               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                                Go to Hifz &amp; Quran
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layout.admin>
