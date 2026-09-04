@php
    $student = $enrollment?->student;
    $work = $record->workEntries();
@endphp

<x-layout.admin title="Daily Record">
    <x-slot name="header">
        Hifz &amp; Quran — Daily Record
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4">
                <a href="{{ route('hifz.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Hifz &amp; Quran
                </a>

                @if($student)
                    <a href="{{ route('students.show', $student->id) }}#hifz-quran" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Back to Student Profile
                    </a>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if($student)
                    <a href="{{ route('students.hifz', $student->id) }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        All Daily Records
                    </a>
                @endif

                <a href="{{ route('hifz.edit', $record->id) }}"
                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                    Edit Record
                </a>
            </div>
        </div>

        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-4">
                    {{-- The photo the student profile shows, when one is on
                         file. A missing or deleted file falls back to the
                         initial rather than to a broken image. --}}
                    <div class="h-16 w-16 rounded-full bg-gray-300 flex items-center justify-center overflow-hidden flex-shrink-0">
                        @if($student?->photo && \Storage::disk('public')->exists($student->photo))
                            <img src="{{ asset('storage/' . $student->photo) }}" class="h-16 w-16 rounded-full object-cover" alt="{{ $student->full_name }}">
                        @else
                            <span class="text-xl font-medium text-gray-700">
                                {{ $student ? mb_strtoupper(mb_substr($student->full_name, 0, 1)) : '?' }}
                            </span>
                        @endif
                    </div>

                    <div>
                        <h2 class="text-xl font-semibold text-gray-800">
                            {{ $student?->full_name ?? 'Unknown student' }}
                        </h2>
                        <p class="text-sm text-gray-600 mt-1">
                            {{ $record->record_date->format('l, d F Y') }}
                        </p>
                    </div>
                </div>
                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $record->recordTypeBadgeClasses() }}">
                    {{ $record->record_type }}
                </span>
            </div>
        </div>

        <!-- The placement this day was recorded under -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Academic Placement</h3>

            {{-- Read back through the stored enrollment, never from the
                 student's current placement columns. A record made in Nazra /
                 Section A still reads as Nazra / Section A after the student
                 is promoted to Hifz / Section B. --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">Registration No.</label>
                    <p class="text-gray-900">{{ $student?->registration_number ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Roll No.</label>
                    <p class="text-gray-900">{{ $student?->roll_number ?: 'Not assigned' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Programme</label>
                    <p class="text-gray-900">{{ $student?->student_type ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Academic Track</label>
                    <p class="text-gray-900">{{ $enrollment?->academic_track ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Academic Session</label>
                    <p class="text-gray-900">{{ $enrollment?->academicSession?->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Department</label>
                    <p class="text-gray-900">{{ $enrollment?->department?->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Class</label>
                    <p class="text-gray-900">{{ $enrollment?->academicClass?->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Section</label>
                    <p class="text-gray-900">{{ $enrollment?->section?->name ?? 'No section' }}</p>
                </div>
            </div>

            <p class="mt-4 text-sm text-gray-500">
                Shown as recorded on this date. A later promotion does not change what this record says.
            </p>
        </div>

        <!-- The day's work -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">
                {{ $record->record_type }} — Daily Work
            </h3>

            @if($work === [])
                <p class="text-gray-500">No work recorded.</p>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($work as $label => $value)
                        <div>
                            <label class="text-sm font-medium text-gray-500">{{ $label }}</label>
                            <p class="text-gray-900">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Teacher and remarks -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Teacher &amp; Remarks</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">Teacher</label>
                    <p class="text-gray-900">
                        @if($record->teacher)
                            <a href="{{ route('teachers.show', $record->teacher->id) }}" class="text-blue-600 hover:text-blue-800">
                                {{ $record->teacher->full_name }}
                            </a>
                        @else
                            Not recorded
                        @endif
                    </p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Recorded</label>
                    <p class="text-gray-900">{{ $record->created_at?->format('d M, Y g:i A') ?? 'N/A' }}</p>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-gray-500">Teacher Remarks</label>
                    <p class="text-gray-900 whitespace-pre-line">{{ $record->remarks ?: 'None' }}</p>
                </div>
            </div>
        </div>
    </div>
</x-layout.admin>
