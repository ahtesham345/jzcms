@php
    $currentFilters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
@endphp

<x-layout.admin title="Hifz Progress">
    <x-slot name="header">
        Hifz &amp; Quran — {{ $recordType }} Progress
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

                <a href="{{ route('hifz.reports', ['record_type' => $recordType]) }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Reports
                </a>
            </div>

            <a href="{{ route('students.hifz', $student->id) }}"
               class="inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                View Daily Records
            </a>
        </div>

        <!-- Student information -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="h-20 w-20 rounded-full bg-gray-300 flex items-center justify-center overflow-hidden flex-shrink-0">
                        @if($student->photo && \Storage::disk('public')->exists($student->photo))
                            <img src="{{ asset('storage/' . $student->photo) }}" class="h-20 w-20 rounded-full object-cover" alt="{{ $student->full_name }}">
                        @else
                            <span class="text-2xl font-medium text-gray-700">{{ mb_strtoupper(mb_substr($student->full_name, 0, 1)) }}</span>
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

                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-emerald-100 text-emerald-800">
                    {{ $recordType }}
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6 border-t border-gray-200 pt-6">
                <div>
                    <label class="text-sm font-medium text-gray-500">Current Program</label>
                    <p class="text-gray-900">{{ $student->student_type }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Current Academic Session</label>
                    <p class="text-gray-900">{{ $currentEnrollment?->academicSession?->name ?? 'No active placement' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Current Madrassa Class</label>
                    <p class="text-gray-900">{{ $currentEnrollment?->academicClass?->name ?? 'No active placement' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Current Section</label>
                    <p class="text-gray-900">{{ $currentEnrollment?->section?->name ?? ($currentEnrollment ? 'No section' : '—') }}</p>
                </div>
            </div>

            {{-- Said plainly: the placement above is where the student is
                 now. Every record below keeps the placement it was made
                 under. --}}
            <p class="mt-4 text-sm text-gray-500">
                The placement above is the student's current one. Each record below shows the class and section it was recorded under.
            </p>
        </div>

        <!-- Progress summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Total Recorded Days</p>
                <p class="text-3xl font-bold text-gray-900">{{ $overall['total'] }}</p>
                <p class="text-sm text-gray-600 mt-2">Across the whole history</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">First Recorded</p>
                <p class="text-3xl font-bold text-gray-900">{{ $overall['first_date']?->format('d M, Y') ?? 'None' }}</p>
                <p class="text-sm text-gray-600 mt-2">Earliest {{ $recordType }} record</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <p class="text-sm font-medium text-gray-600 mb-1">Latest Recorded</p>
                <p class="text-3xl font-bold text-gray-900">{{ $overall['last_date']?->format('d M, Y') ?? 'None' }}</p>
                <p class="text-sm text-gray-600 mt-2">Most recent {{ $recordType }} record</p>
            </div>
        </div>

        <!-- Most recent work -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Most Recent {{ $recordType }} Work</h3>

            @if($latest === null)
                <p class="text-gray-500">Nothing recorded yet.</p>
            @else
                <p class="text-sm text-gray-600 mb-4">
                    Recorded on {{ $latest->record_date->format('l, d F Y') }},
                    under {{ $latest->studentAcademicEnrollment?->academicClass?->name ?? 'N/A' }}
                    @if($latest->studentAcademicEnrollment?->section)
                        / {{ $latest->studentAcademicEnrollment->section->name }}
                    @endif
                    @if($latest->studentAcademicEnrollment?->academicSession)
                        ({{ $latest->studentAcademicEnrollment->academicSession->name }})
                    @endif
                </p>

                {{-- Copied straight out of the record. "1 page" is shown as
                     "1 page"; nothing is normalised or converted. --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach($workFields as $field)
                        <div>
                            <label class="text-sm font-medium text-gray-500">{{ $fieldLabels[$field] ?? $field }}</label>
                            <p class="text-gray-900">{{ $latest->{$field} ?: '—' }}</p>
                        </div>
                    @endforeach

                    <div>
                        <label class="text-sm font-medium text-gray-500">Teacher</label>
                        <p class="text-gray-900">{{ $latest->teacher?->full_name ?? 'Not recorded' }}</p>
                    </div>
                </div>

                @if($latest->remarks)
                    <div class="mt-4">
                        <label class="text-sm font-medium text-gray-500">Teacher Remarks</label>
                        <p class="text-gray-900 whitespace-pre-line">{{ $latest->remarks }}</p>
                    </div>
                @endif
            @endif
        </div>

        <div class="bg-white rounded-lg shadow-sm">
            <!-- Period filter -->
            <div class="px-6 py-4 border-b border-gray-200">
                <form method="GET" action="{{ route('students.hifz.progress', $student->id) }}" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                        <div class="flex items-end space-x-2">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Apply Period
                            </button>
                            @if($currentFilters !== [])
                                <a href="{{ route('students.hifz.progress', $student->id) }}"
                                   class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                    Clear
                                </a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>

            <!-- Period summary -->
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    Period Summary
                    <span class="text-sm font-normal text-gray-600">
                        @if($hasFilters)
                            ({{ $filters['date_from'] ?? 'start' }} to {{ $filters['date_to'] ?? 'today' }})
                        @else
                            (whole history)
                        @endif
                    </span>
                </h3>

                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6 mt-4">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Recorded Days</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $period['recorded_days'] }}</p>
                    </div>

                    {{-- Counts of days on which each kind of work was written
                         down. Not a measure of how much was memorised: the
                         quantities are text and nothing sums them. --}}
                    @foreach($period['days_by_label'] as $label => $count)
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-1">Days with {{ $label }}</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $count }}</p>
                        </div>
                    @endforeach

                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Teachers Involved</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $period['teachers'] }}</p>
                    </div>
                </div>

                @if($periodTeachers->isNotEmpty())
                    <p class="mt-4 text-sm text-gray-600">
                        <span class="font-medium text-gray-800">Teachers:</span>
                        {{ $periodTeachers->pluck('full_name')->implode(', ') }}
                    </p>
                @endif

                <p class="mt-4 text-sm text-gray-500">
                    These are counts of recorded days. Quran quantities are never totalled or converted.
                </p>
            </div>

            <!-- Daily history -->
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Daily History</h3>
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
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Session</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                @foreach($workFields as $field)
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ $fieldLabels[$field] ?? $field }}
                                    </th>
                                @endforeach
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            {{-- Newest first. Session, department, class and
                                 section all come from the enrollment each
                                 record was written against, never from the
                                 student's current placement. --}}
                            @foreach($records as $record)
                                @php($enrollment = $record->studentAcademicEnrollment)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $record->record_date->format('d M, Y') }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment?->academicSession?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment?->department?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment?->academicClass?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment?->section?->name ?? 'No section' }}</td>
                                    @foreach($workFields as $field)
                                        <td class="px-4 py-4 text-sm text-gray-700 whitespace-nowrap">{{ $record->{$field} ?: '—' }}</td>
                                    @endforeach
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $record->teacher?->full_name ?? 'Not recorded' }}</td>
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
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No {{ $recordType }} records in this period.</h3>
                    @if($hasFilters)
                        <p class="mt-1 text-sm text-gray-500">Try widening the date range.</p>
                    @else
                        <p class="mt-1 text-sm text-gray-500">
                            Daily records are entered on the Hifz &amp; Quran roster.
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layout.admin>
