@php
    $student = $enrollment?->student;
@endphp

<x-layout.admin title="Result">
    <x-slot name="header">
        Results — {{ $result->term }} {{ $result->test_type }}
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4">
                <a href="{{ route('results.index', ['term' => $result->term]) }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Results
                </a>

                @if($student)
                    <a href="{{ route('students.show', $student->id) }}#results" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Back to Student Profile
                    </a>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if($student)
                    {{-- The rest of this student's madrassa results, so one
                         result is never a dead end. --}}
                    <a href="{{ route('students.results', $student->id) }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        Result History
                    </a>
                @endif

                <a href="{{ route('results.edit', $result->id) }}"
                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                    Edit Result
                </a>
            </div>
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
                        <h2 class="text-xl font-semibold text-gray-800">
                            {{ $student?->full_name ?? 'Unknown student' }}
                        </h2>
                        <p class="text-sm text-gray-600 mt-1">
                            {{ $student?->registration_number ?? 'N/A' }}
                            @if($student?->roll_number) &middot; Roll No. {{ $student->roll_number }} @endif
                        </p>
                    </div>
                </div>

                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $result->gradeBadgeClasses() }}">
                    Grade {{ $result->grade }}
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 border-t border-gray-200 pt-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">Student</label>
                    <p class="text-gray-900">
                        @if($student)
                            <a href="{{ route('students.show', $student->id) }}" class="text-blue-600 hover:text-blue-800">{{ $student->full_name }}</a>
                        @else
                            Unknown student
                        @endif
                    </p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Registration Number</label>
                    <p class="text-gray-900">{{ $student?->registration_number ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Roll Number</label>
                    <p class="text-gray-900">{{ $student?->roll_number ?: 'Not assigned' }}</p>
                </div>
            </div>
        </div>

        <!-- Enrollment at Time of Result -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Enrollment at Time of Result</h3>

            {{-- Read back through the enrollment the result was recorded
                 against, never from the student's current placement. That is
                 what keeps this the class and section the test was actually
                 sat in after the student has been promoted out of them. --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">Session</label>
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
                <div>
                    <label class="text-sm font-medium text-gray-500">Programme</label>
                    <p class="text-gray-900">{{ $student?->student_type ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Track</label>
                    <p class="text-gray-900">{{ $enrollment?->academic_track ?? 'N/A' }}</p>
                </div>
            </div>

            {{-- Said plainly rather than left for the reader to trip over:
                 the programme is a column on the student, so unlike the four
                 above it is the current one. The department is the
                 historical placement. --}}
            <p class="mt-4 text-sm text-gray-500">
                Session, department, class and section are the ones this result was recorded under. Programme is
                recorded on the student, so it shows the student's current programme.
            </p>
        </div>

        <!-- Result -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Result</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">Term</label>
                    <p class="text-gray-900">{{ $result->term }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Test Type</label>
                    <p class="text-gray-900">{{ $result->test_type }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Result Date</label>
                    <p class="text-gray-900">{{ $result->result_date?->format('d M, Y') ?? 'No date' }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6 border-t border-gray-200 pt-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">Total Marks</label>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format((float) $result->total_marks, 2) }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Obtained Marks</label>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format((float) $result->obtained_marks, 2) }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Percentage</label>
                    {{-- The stored value, formatted. Nothing on this page
                         divides one mark by another: the application computed
                         both of these on save and this only reads them. --}}
                    <p class="text-2xl font-bold text-gray-900">{{ $result->formattedPercentage() }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Grade</label>
                    <p class="text-2xl font-bold text-gray-900">{{ $result->grade }}</p>
                </div>
            </div>

            <p class="mt-4 text-sm text-gray-500">
                Percentage and grade are calculated by the system from the marks above.
            </p>
        </div>

        <!-- Remarks -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Remarks</h3>
            <p class="text-gray-700 whitespace-pre-line">{{ $result->remarks ?: 'No remarks recorded.' }}</p>
        </div>
    </div>
</x-layout.admin>
