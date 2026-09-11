<x-layout.admin title="View Admission Application">
    <x-slot name="header">
        View Admission Application
    </x-slot>

    @php
        $statusClasses = $application->statusBadgeClasses();
    @endphp

    {{-- The modal reopens automatically when approval validation fails. --}}
    <div class="space-y-6" x-data="{ approveOpen: {{ $errors->any() ? 'true' : 'false' }} }">
        <!-- Page Header -->
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center space-x-4">
                <!-- Photo -->
                <div class="flex-shrink-0">
                    <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center border-2 border-gray-200 overflow-hidden">
                        @if($application->photo && \Storage::disk('public')->exists($application->photo))
                            <img src="{{ asset('storage/' . $application->photo) }}"
                                 class="w-20 h-20 rounded-full object-cover"
                                 alt="{{ $application->student_name }}">
                        @else
                            <svg class="w-10 h-10 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                            </svg>
                        @endif
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-gray-800">{{ $application->student_name }}</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Application Number: <span class="font-medium">{{ $application->application_number }}</span>
                    </p>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $statusClasses }}">
                    {{ $application->status }}
                </span>
                <x-whatsapp-button :application="$application" />
                @if($application->canBeApproved())
                    <button
                        type="button"
                        @click="approveOpen = true"
                        class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Approve Admission
                    </button>
                @endif
                <a
                    href="{{ route('admissions.index') }}"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                >
                    Back to List
                </a>
                <a
                    href="{{ route('admissions.edit', $application->id) }}"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Edit Application
                </a>
                <form
                    action="{{ route('admissions.destroy', $application->id) }}"
                    method="POST"
                    class="inline"
                    onsubmit="return confirm('Are you sure you want to delete application {{ $application->application_number }}? This action cannot be undone.');"
                >
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
                    >
                        Delete
                    </button>
                </form>
            </div>
        </div>

        @if($application->isApproved() && $application->student)
            <!-- Admitted Student -->
            <div class="bg-white rounded-lg shadow-sm border-l-4 border-green-500">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-800">Admitted Student</h3>
                    <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                        Admission Approved
                    </span>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Registration Number</label>
                        <p class="text-base font-medium text-gray-900">{{ $application->student->registration_number }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Student Name</label>
                        <p class="text-base font-medium text-gray-900">{{ $application->student->full_name }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <a
                            href="{{ route('students.show', $application->student->id) }}"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            View Student Profile
                        </a>
                    </div>
                </div>
            </div>
        @endif

        <!-- Application Information -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-base font-semibold text-gray-800">Application Information</h3>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Application Number</label>
                    <p class="text-base font-medium text-gray-900">{{ $application->application_number }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Submitted Date</label>
                    <p class="text-base font-medium text-gray-900">{{ $application->created_at->format('d M, Y h:i A') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Last Updated</label>
                    <p class="text-base font-medium text-gray-900">{{ $application->updated_at->format('d M, Y h:i A') }}</p>
                </div>
            </div>
        </div>

        <!-- Student Information -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-base font-semibold text-gray-800">Student Information</h3>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Student Name</label>
                    <p class="text-base font-medium text-gray-900">{{ $application->student_name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Date of Birth</label>
                    <p class="text-base font-medium text-gray-900">
                        {{ $application->date_of_birth ? $application->date_of_birth->format('d M, Y') : 'N/A' }}
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Gender</label>
                    <p class="text-base font-medium text-gray-900">{{ $application->gender }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">B-Form Number</label>
                    <p class="text-base font-medium text-gray-900">{{ $application->b_form_number ?? 'N/A' }}</p>
                </div>
            </div>
        </div>

        <!-- Parent Information -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-base font-semibold text-gray-800">Parent Information</h3>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Father Name</label>
                    <p class="text-base font-medium text-gray-900">{{ $application->father_name }}</p>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-base font-semibold text-gray-800">Contact Information</h3>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Father Mobile</label>
                    <p class="text-base font-medium text-gray-900">{{ $application->father_mobile }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Mother Mobile</label>
                    <p class="text-base font-medium text-gray-900">{{ $application->mother_mobile ?? 'N/A' }}</p>
                </div>
            </div>
        </div>

        <!-- Address -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-base font-semibold text-gray-800">Address</h3>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Permanent Address</label>
                    <p class="text-base font-medium text-gray-900 whitespace-pre-line">{{ $application->permanent_address ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Current Address</label>
                    <p class="text-base font-medium text-gray-900 whitespace-pre-line">{{ $application->current_address ?? 'N/A' }}</p>
                </div>
            </div>
        </div>

        <!-- Admission Information -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-base font-semibold text-gray-800">Admission Information</h3>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Student Type</label>
                    <p class="mt-1">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                            {{ $application->student_type }}
                        </span>
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Admission Date</label>
                    <p class="text-base font-medium text-gray-900">
                        {{ $application->admission_date ? $application->admission_date->format('d M, Y') : 'N/A' }}
                    </p>
                </div>
                @if($application->madrassaClass)
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">
                            {{ $application->schoolClass ? 'Madrassa Class' : 'Class Applied For' }}
                        </label>
                        <p class="text-base font-medium text-gray-900">
                            {{ $application->madrassaClass->name }}
                            <span class="text-sm text-gray-500">({{ $application->madrassaClass->department?->name }})</span>
                        </p>
                    </div>
                @endif
                @if($application->schoolClass)
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">School Class</label>
                        <p class="text-base font-medium text-gray-900">
                            {{ $application->schoolClass->name }}
                            <span class="text-sm text-gray-500">({{ $application->schoolClass->department?->name }})</span>
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Admission Test -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-gray-800">Admission Test</h3>
                <div class="flex items-center space-x-2">
                    @if($application->hasTestSchedule() && $application->whatsappNumber() === null)
                        <span class="text-sm text-gray-400" title="{{ $application->father_mobile }} is not a valid Pakistani mobile number">
                            No valid mobile
                        </span>
                    @endif
                    <x-whatsapp-button :application="$application" compact />
                    @if($application->test_result)
                        <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $application->testResultBadgeClasses() }}">
                            {{ $application->test_result }}
                        </span>
                    @elseif($application->hasTestSchedule())
                        <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800">
                            Scheduled
                        </span>
                    @else
                        <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-800">
                            Not Scheduled
                        </span>
                    @endif
                </div>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Test Date</label>
                    <p class="text-base font-medium text-gray-900">
                        {{ $application->test_date ? $application->test_date->format('d M, Y') : 'Not scheduled' }}
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Test Time</label>
                    <p class="text-base font-medium text-gray-900">
                        {{ $application->formattedTestTime() ?? 'Not scheduled' }}
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Test Marks</label>
                    <p class="text-base font-medium text-gray-900">
                        {{ $application->test_marks !== null ? $application->test_marks : 'Not recorded' }}
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Test Result</label>
                    @if($application->test_result)
                        <p class="mt-1">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $application->testResultBadgeClasses() }}">
                                {{ $application->test_result }}
                            </span>
                        </p>
                    @else
                        <p class="text-base font-medium text-gray-900">Not recorded</p>
                    @endif
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-500 mb-1">Test Remarks</label>
                    @if($application->test_remarks)
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-base text-gray-900 whitespace-pre-line">{{ $application->test_remarks }}</p>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">No test remarks have been recorded.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Important Instructions Agreement -->
        <div class="bg-white rounded-lg shadow-sm" x-data="{ instructionsOpen: false }">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-800">Important Instructions Agreement</h3>
                @if($application->instructions_accepted)
                    <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                        Accepted
                    </span>
                @else
                    <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-800">
                        Not Accepted
                    </span>
                @endif
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Guardian Agreement</label>
                        <p class="text-base font-medium text-gray-900">
                            {{ $application->instructions_accepted ? 'Accepted' : 'Not Accepted' }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Accepted On</label>
                        <p class="text-base font-medium text-gray-900">
                            {{ $application->instructions_accepted_at?->format('d M, Y h:i A') ?? 'N/A' }}
                        </p>
                    </div>
                </div>

                <!-- Collapsible, read only -->
                <div class="mt-6 pt-4 border-t border-gray-200">
                    <button
                        type="button"
                        @click="instructionsOpen = !instructionsOpen"
                        class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800"
                    >
                        <svg class="w-4 h-4 mr-1 transition-transform duration-200"
                             :class="instructionsOpen ? 'transform rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                        <span x-text="instructionsOpen ? 'Hide instructions' : 'View instructions'">View instructions</span>
                    </button>

                    <div x-show="instructionsOpen" x-cloak class="mt-4 border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <x-admission-instructions :application="$application" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-base font-semibold text-gray-800">Notes</h3>
            </div>
            <div class="p-6">
                @if($application->notes)
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-base text-gray-900 whitespace-pre-line">{{ $application->notes }}</p>
                    </div>
                @else
                    <p class="text-sm text-gray-500">No notes have been added to this application.</p>
                @endif
            </div>
        </div>

        <!-- Status -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-base font-semibold text-gray-800">Status</h3>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Current Status</label>
                    <p class="mt-1">
                        <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $statusClasses }}">
                            {{ $application->status }}
                        </span>
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Status Last Updated</label>
                    <p class="text-base font-medium text-gray-900">{{ $application->updated_at->format('d M, Y h:i A') }}</p>
                </div>
            </div>
        </div>

        @if($application->canBeApproved())
            <!-- Approval Confirmation Modal -->
            <div
                x-show="approveOpen"
                x-cloak
                class="fixed inset-0 z-50 overflow-y-auto"
                aria-labelledby="approve-modal-title"
                role="dialog"
                aria-modal="true"
            >
                <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <!-- Overlay -->
                    <div
                        x-show="approveOpen"
                        x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="ease-in duration-200"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        @click="approveOpen = false"
                        class="fixed inset-0 bg-gray-900 bg-opacity-50 transition-opacity"
                    ></div>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                    <!-- Panel -->
                    <div
                        x-show="approveOpen"
                        x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave="ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        @click.stop
                        class="inline-block w-full max-w-2xl my-8 text-left align-middle bg-white rounded-lg shadow-xl transform transition-all sm:align-middle"
                    >
                        <form method="POST" action="{{ route('admissions.approve', $application->id) }}">
                            @csrf

                            <!-- Modal Header -->
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 id="approve-modal-title" class="text-lg font-semibold text-gray-800">
                                    Confirm Admission Approval
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    This creates an active student record for
                                    <span class="font-medium">{{ $application->student_name }}</span>
                                    ({{ $application->application_number }}) and marks the application as Approved.
                                    The registration number is generated automatically.
                                </p>
                            </div>

                            <!-- Modal Body -->
                            <div class="px-6 py-4 max-h-[60vh] overflow-y-auto">
                                @if($errors->any())
                                    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                                        <p class="text-sm text-red-800">Please correct the errors below before approving.</p>
                                    </div>
                                @endif

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Academic Session -->
                                    <div>
                                        <label for="academic_session_id" class="block text-sm font-medium text-gray-700 mb-1">
                                            Academic Session <span class="text-red-500">*</span>
                                        </label>
                                        <select name="academic_session_id" id="academic_session_id" required
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('academic_session_id') border-red-500 @enderror">
                                            <option value="">Select Session</option>
                                            @foreach($academicSessions as $session)
                                                <option value="{{ $session->id }}" {{ old('academic_session_id') == $session->id ? 'selected' : '' }}>{{ $session->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('academic_session_id')
                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    @php
                                        $madrassaClass = $application->madrassaClass;
                                        $schoolClass = $application->schoolClass;
                                        $spansBothTracks = $madrassaClass && $schoolClass;
                                    @endphp

                                    @if($madrassaClass || $schoolClass)
                                        {{-- Department and class come from the parent's application and
                                             are not submitted; the server reads them from the record. --}}
                                        @if($madrassaClass)
                                            <div class="md:col-span-2 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                                <h4 class="text-sm font-semibold text-gray-800 mb-3">
                                                    {{ $spansBothTracks ? 'Madrassa' : 'Placement' }}
                                                </h4>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                                                        <input type="text" value="{{ $madrassaClass->department?->name }}" readonly disabled
                                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600 cursor-not-allowed">
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                                                        <input type="text" value="{{ $madrassaClass->name }}" readonly disabled
                                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600 cursor-not-allowed">
                                                    </div>
                                                    <div class="md:col-span-2">
                                                        @php $madrassaField = $spansBothTracks ? 'madrassa_section_id' : 'section_id'; @endphp
                                                        <label for="{{ $madrassaField }}" class="block text-sm font-medium text-gray-700 mb-1">
                                                            {{ $spansBothTracks ? 'Madrassa Section' : 'Section' }}
                                                        </label>
                                                        <select name="{{ $madrassaField }}" id="{{ $madrassaField }}"
                                                            @disabled($madrassaSections->isEmpty())
                                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @if($madrassaSections->isEmpty()) bg-gray-100 text-gray-500 @endif">
                                                            <option value="">No section</option>
                                                            @foreach($madrassaSections as $section)
                                                                <option value="{{ $section->id }}" {{ old($madrassaField) == $section->id ? 'selected' : '' }}>{{ $section->name }}</option>
                                                            @endforeach
                                                        </select>
                                                        <p class="mt-1 text-sm text-gray-500">
                                                            @if($madrassaSections->isEmpty())
                                                                This class has no sections. The student can still be approved.
                                                            @else
                                                                Optional. Only sections of {{ $madrassaClass->name }} are listed.
                                                            @endif
                                                        </p>
                                                        @error($madrassaField)
                                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        @if($schoolClass)
                                            <div class="md:col-span-2 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                                <h4 class="text-sm font-semibold text-gray-800 mb-3">
                                                    {{ $spansBothTracks ? 'School' : 'Placement' }}
                                                </h4>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                                                        <input type="text" value="{{ $schoolClass->department?->name }}" readonly disabled
                                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600 cursor-not-allowed">
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                                                        <input type="text" value="{{ $schoolClass->name }}" readonly disabled
                                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600 cursor-not-allowed">
                                                    </div>
                                                    <div class="md:col-span-2">
                                                        @php $schoolField = $spansBothTracks ? 'school_section_id' : 'section_id'; @endphp
                                                        <label for="{{ $schoolField }}" class="block text-sm font-medium text-gray-700 mb-1">
                                                            {{ $spansBothTracks ? 'School Section' : 'Section' }}
                                                        </label>
                                                        <select name="{{ $schoolField }}" id="{{ $schoolField }}"
                                                            @disabled($schoolSections->isEmpty())
                                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @if($schoolSections->isEmpty()) bg-gray-100 text-gray-500 @endif">
                                                            <option value="">No section</option>
                                                            @foreach($schoolSections as $section)
                                                                <option value="{{ $section->id }}" {{ old($schoolField) == $section->id ? 'selected' : '' }}>{{ $section->name }}</option>
                                                            @endforeach
                                                        </select>
                                                        <p class="mt-1 text-sm text-gray-500">
                                                            @if($schoolSections->isEmpty())
                                                                This class has no sections. The student can still be approved.
                                                            @else
                                                                Optional. Only sections of {{ $schoolClass->name }} are listed.
                                                            @endif
                                                        </p>
                                                        @error($schoolField)
                                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        <p class="md:col-span-2 -mt-1 text-sm text-gray-500">
                                            Department and class are taken from the admission application and cannot be changed here.
                                        </p>
                                    @else
                                        {{-- Legacy or admin created application: no class of its own,
                                             so the placement is still chosen here. --}}
                                        <div>
                                            <label for="department_id" class="block text-sm font-medium text-gray-700 mb-1">
                                                Department <span class="text-red-500">*</span>
                                            </label>
                                            <select name="department_id" id="department_id" required
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('department_id') border-red-500 @enderror">
                                                <option value="">Select Department</option>
                                                @foreach($departments as $department)
                                                    <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('department_id')
                                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        {{-- The class is picked here, so the section list follows it. --}}
                                        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4"
                                             x-data="{
                                                 classId: '{{ old('academic_class_id') }}',
                                                 sectionsByClass: {{ Js::from($sectionsByClass) }},
                                                 get sections() { return this.sectionsByClass[this.classId] ?? [] },
                                             }">
                                            <div>
                                                <label for="academic_class_id" class="block text-sm font-medium text-gray-700 mb-1">
                                                    Class <span class="text-red-500">*</span>
                                                </label>
                                                <select name="academic_class_id" id="academic_class_id" required
                                                    x-model="classId"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('academic_class_id') border-red-500 @enderror">
                                                    <option value="">Select Class</option>
                                                    @foreach($academicClasses as $class)
                                                        <option value="{{ $class->id }}">{{ $class->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error('academic_class_id')
                                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div>
                                                <label for="section_id" class="block text-sm font-medium text-gray-700 mb-1">
                                                    Section
                                                </label>
                                                <select name="section_id" id="section_id"
                                                    :disabled="sections.length === 0"
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('section_id') border-red-500 @enderror">
                                                    <option value="">No section</option>
                                                    <template x-for="option in sections" :key="option.id">
                                                        <option :value="option.id" x-text="option.name"></option>
                                                    </template>
                                                </select>
                                                <p class="mt-1 text-sm text-gray-500"
                                                   x-text="!classId ? 'Choose a class first.' : (sections.length === 0 ? 'This class has no sections. The student can still be approved.' : 'Optional. Only sections of the selected class are listed.')"></p>
                                                @error('section_id')
                                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Admission Date -->
                                    <div>
                                        <label for="approve_admission_date" class="block text-sm font-medium text-gray-700 mb-1">
                                            Admission Date <span class="text-red-500">*</span>
                                        </label>
                                        <input type="date" name="admission_date" id="approve_admission_date" required
                                            value="{{ old('admission_date', $application->admission_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('admission_date') border-red-500 @enderror">
                                        @error('admission_date')
                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Emergency Contact -->
                                    <div>
                                        <label for="emergency_contact" class="block text-sm font-medium text-gray-700 mb-1">
                                            Emergency Contact <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="emergency_contact" id="emergency_contact" required
                                            value="{{ old('emergency_contact', $application->father_mobile) }}"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('emergency_contact') border-red-500 @enderror">
                                        @error('emergency_contact')
                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Resident Type -->
                                    <div>
                                        <label for="resident_type" class="block text-sm font-medium text-gray-700 mb-1">
                                            Resident Type <span class="text-red-500">*</span>
                                        </label>
                                        <select name="resident_type" id="resident_type" required
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('resident_type') border-red-500 @enderror">
                                            <option value="">Select Resident Type</option>
                                            <option value="Local Resident" {{ old('resident_type') === 'Local Resident' ? 'selected' : '' }}>Local Resident</option>
                                            <option value="Outside Resident" {{ old('resident_type') === 'Outside Resident' ? 'selected' : '' }}>Outside Resident</option>
                                        </select>
                                        @error('resident_type')
                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Roll Number -->
                                    <div>
                                        <label for="roll_number" class="block text-sm font-medium text-gray-700 mb-1">
                                            Roll Number
                                        </label>
                                        <input type="text" name="roll_number" id="roll_number"
                                            value="{{ old('roll_number') }}"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('roll_number') border-red-500 @enderror">
                                        @error('roll_number')
                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Medical Information -->
                                    <div class="md:col-span-2">
                                        <label for="medical_information" class="block text-sm font-medium text-gray-700 mb-1">
                                            Medical Information
                                        </label>
                                        <textarea name="medical_information" id="medical_information" rows="2"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('medical_information') border-red-500 @enderror">{{ old('medical_information') }}</textarea>
                                        @error('medical_information')
                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Footer -->
                            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end space-x-3">
                                <button type="button" @click="approveOpen = false"
                                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-white transition-colors">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                    Confirm &amp; Create Student
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-layout.admin>
