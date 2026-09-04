<x-layout.admin title="Edit Academic Enrollment">
    <x-slot name="header">
        Edit Academic Enrollment
    </x-slot>

    <div class="max-w-4xl">
        <!-- Back Button -->
        <div class="mb-4">
            <a href="{{ route('students.show', $student->id) }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Student Profile
            </a>
        </div>

        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    {{ $student->full_name }} — {{ $student->registration_number }}
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    Editing the {{ $enrollment->academic_track }} enrollment for
                    {{ $enrollment->academicSession?->name ?? 'an unrecorded session' }}.
                </p>
            </div>

            <div class="p-6">
                <x-enrollment-form
                    :action="route('students.enrollments.update', [$student->id, $enrollment->id])"
                    method="PUT"
                    :enrollment="$enrollment"
                    :academic-sessions="$academicSessions"
                    :departments="$departments"
                    :classes-by-department="$classesByDepartment"
                    :sections-by-class="$sectionsByClass"
                    :academic-tracks="$academicTracks"
                    :enrollment-statuses="$enrollmentStatuses"
                    :cancel-url="route('students.show', $student->id)"
                    submit-label="Update Enrollment"
                />
            </div>
        </div>
    </div>
</x-layout.admin>
