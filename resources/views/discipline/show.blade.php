<x-layout.admin title="Discipline Record">
    <x-slot name="header">
        Discipline — {{ $record->category }} Incident
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4">
                <a href="{{ route('discipline.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Discipline
                </a>

                @if($student)
                    <a href="{{ route('students.discipline', $student->id) }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Back to History
                    </a>
                @endif
            </div>

            <a href="{{ route('discipline.edit', $record->id) }}"
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                Edit
            </a>
        </div>

        <!-- Student -->
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
                        <h2 class="text-xl font-semibold text-gray-800">{{ $student?->full_name ?? 'Unknown student' }}</h2>
                        <p class="text-sm text-gray-600 mt-1">
                            {{ $student?->registration_number ?? 'N/A' }}
                            @if($student?->roll_number) &middot; Roll No. {{ $student->roll_number }} @endif
                        </p>
                    </div>
                </div>

                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $record->severityBadgeClasses() }}">
                    {{ $record->severity }} Severity
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 border-t border-gray-200 pt-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">Name</label>
                    <p class="text-gray-900">
                        @if($student)
                            <a href="{{ route('students.show', $student->id) }}" class="text-blue-600 hover:text-blue-800">{{ $student->full_name }}</a>
                        @else
                            Unknown student
                        @endif
                    </p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Registration No.</label>
                    <p class="text-gray-900">{{ $student?->registration_number ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Roll No.</label>
                    <p class="text-gray-900">{{ $student?->roll_number ?: 'Not assigned' }}</p>
                </div>
            </div>
        </div>

        <!-- Incident -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Incident</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">Date</label>
                    <p class="text-gray-900">{{ $record->date?->format('d M, Y') ?? 'No date' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Category</label>
                    <p class="text-gray-900">{{ $record->category }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Severity</label>
                    <p class="mt-1">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $record->severityBadgeClasses() }}">
                            {{ $record->severity }}
                        </span>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 border-t border-gray-200 pt-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">Action Taken</label>
                    <p class="text-gray-900">{{ $record->action_taken ?: 'None recorded' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Recorded By</label>
                    {{-- Taken from the authenticated user when the record
                         was saved. Nothing a request sends can change it. --}}
                    <p class="text-gray-900">{{ $record->recorder?->name ?? 'Not recorded' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Created At</label>
                    <p class="text-gray-900">{{ $record->created_at?->format('d M, Y h:i A') ?? 'N/A' }}</p>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Description</h3>
            <p class="text-gray-700 whitespace-pre-line">{{ $record->description }}</p>
        </div>

        <!-- Remarks -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Remarks</h3>
            <p class="text-gray-700 whitespace-pre-line">{{ $record->remarks ?: 'No remarks recorded.' }}</p>
        </div>
    </div>
</x-layout.admin>
