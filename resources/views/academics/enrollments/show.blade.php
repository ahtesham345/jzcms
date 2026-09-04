@php
    $student = $record->student;

    // The group this record belongs to, for the drill-down link.
    $groupParameters = array_filter([
        'session' => $record->academic_session_id,
        'track' => $record->academic_track,
        'department' => $record->department_id,
        'class' => $record->academic_class_id,
        'section' => $record->section_id,
    ], fn ($value) => $value !== null);
@endphp

<x-layout.admin title="Academic Record">
    <x-slot name="header">
        Academic Record
    </x-slot>

    <div class="max-w-6xl space-y-6">
        <!-- Back Button -->
        <div>
            <a href="{{ route('academics.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Academic Management
            </a>
        </div>

        <!-- Student Information -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="px-6 py-6 bg-gradient-to-r from-blue-600 to-blue-700">
                <div class="flex flex-col sm:flex-row items-center sm:items-start space-y-4 sm:space-y-0 sm:space-x-6">
                    <div class="flex-shrink-0">
                        <div class="w-24 h-24 rounded-full bg-white/20 flex items-center justify-center border-4 border-white/30 overflow-hidden">
                            @if($student->photo && \Storage::disk('public')->exists($student->photo))
                                <img src="{{ asset('storage/' . $student->photo) }}" class="w-24 h-24 rounded-full object-cover" alt="{{ $student->full_name }}">
                            @else
                                <svg class="w-12 h-12 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </div>
                    </div>

                    <div class="flex-1 text-center sm:text-left">
                        {{-- Links back to the existing student profile rather
                             than repeating student management here. --}}
                        <a href="{{ route('students.show', $student->id) }}" class="text-2xl font-bold text-white hover:underline">
                            {{ $student->full_name }}
                        </a>
                        <p class="text-blue-100 mt-1">Registration No: {{ $student->registration_number }}</p>
                        <p class="text-blue-100">Roll No: {{ $student->roll_number ?? 'N/A' }}</p>
                        <div class="flex flex-wrap gap-2 justify-center sm:justify-start mt-3">
                            <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-white/20 text-white">
                                {{ $student->student_type }}
                            </span>
                            <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full
                                {{ $student->student_status === 'Active' ? 'bg-green-100 text-green-800' :
                                   ($student->student_status === 'Passed' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800') }}">
                                {{ $student->student_status }}
                            </span>
                            <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-white/20 text-white">
                                {{ $record->academic_track }} track
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-col space-y-2">
                        <a href="{{ route('students.show', $student->id) }}"
                           class="inline-flex items-center justify-center px-4 py-2 bg-white text-blue-600 rounded-lg hover:bg-blue-50 transition-colors">
                            View Student
                        </a>
                        @if($student->canBePromoted())
                            <a href="{{ route('students.promote', $student->id) }}"
                               class="inline-flex items-center justify-center px-4 py-2 bg-white text-green-700 rounded-lg hover:bg-green-50 transition-colors">
                                Promote
                            </a>
                        @endif
                        <a href="{{ route('academics.groups', $groupParameters) }}"
                           class="inline-flex items-center justify-center px-4 py-2 bg-white/20 text-white rounded-lg hover:bg-white/30 transition-colors">
                            View Group
                        </a>
                    </div>
                </div>
            </div>

            <!-- Current Academic Placement -->
            <div class="px-6 py-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Current Academic Placement</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Academic Session</label>
                        <p class="text-gray-900">{{ $record->academicSession?->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Academic Track</label>
                        <p class="text-gray-900">{{ $record->academic_track }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Department</label>
                        <p class="text-gray-900">{{ $record->department?->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Class</label>
                        <p class="text-gray-900">{{ $record->academicClass?->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Section</label>
                        <p class="text-gray-900">{{ $record->section?->name ?? 'No section' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Enrollment Status</label>
                        <p class="mt-1">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $record->statusBadgeClasses() }}">
                                {{ $record->status }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Enrollment Start Date</label>
                        <p class="text-gray-900">{{ $record->start_date?->format('d M, Y') ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Enrollment End Date</label>
                        <p class="text-gray-900">{{ $record->end_date?->format('d M, Y') ?? '—' }}</p>
                    </div>
                    <div class="md:col-span-2 lg:col-span-3">
                        <label class="text-sm font-medium text-gray-500">Notes</label>
                        @if($record->notes)
                            <div class="mt-1 bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <p class="text-gray-900 whitespace-pre-line">{{ $record->notes }}</p>
                            </div>
                        @else
                            <p class="text-gray-900">N/A</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if($otherActiveTrack)
            <!-- Other Active Academic Track -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Other Active Academic Track</h3>
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <p class="text-gray-900 font-medium">
                            {{ $otherActiveTrack->academicSession?->name ?? 'N/A' }}
                            / {{ $otherActiveTrack->academic_track }}
                            / {{ $otherActiveTrack->academicClass?->name ?? 'N/A' }}
                            / {{ $otherActiveTrack->section?->name ?? 'No section' }}
                        </p>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ $otherActiveTrack->department?->name ?? 'N/A' }} department, enrolled
                            {{ $otherActiveTrack->start_date?->format('d M, Y') ?? 'N/A' }}
                        </p>
                    </div>
                    <a href="{{ route('academics.enrollments.show', $otherActiveTrack->id) }}"
                       class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                        View Academic Record
                    </a>
                </div>
            </div>
        @endif

        <!-- Academic History for this track -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">{{ $record->academic_track }} Academic History</h3>
                <p class="text-sm text-gray-600 mt-1">
                    Only {{ $record->academic_track }} enrollments. The other track keeps its own history.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Session</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Date</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        {{-- Oldest first: the progression reads downwards.
                             Master data that has since been retired is still
                             named here, from the enrollment's own relations. --}}
                        @foreach($trackHistory as $entry)
                            <tr class="hover:bg-gray-50 {{ $entry->id === $record->id ? 'bg-blue-50' : '' }}">
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $entry->academicSession?->name ?? 'N/A' }}
                                    @if($entry->id === $record->id)
                                        <span class="ml-1 inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">This record</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $entry->department?->name ?? 'N/A' }}</td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $entry->academicClass?->name ?? 'N/A' }}</td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $entry->section?->name ?? 'No section' }}</td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $entry->statusBadgeClasses() }}">
                                        {{ $entry->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $entry->start_date?->format('d M, Y') ?? 'N/A' }}</td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $entry->end_date?->format('d M, Y') ?? '—' }}</td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                    @if($entry->id === $record->id)
                                        <span class="text-gray-400">Viewing</span>
                                    @else
                                        <a href="{{ route('academics.enrollments.show', $entry->id) }}" class="text-blue-600 hover:text-blue-800">
                                            View
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layout.admin>
