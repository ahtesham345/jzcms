<x-layout.admin title="Edit Admission Application">
    <x-slot name="header">
        Edit Admission Application
    </x-slot>

    <div class="bg-white rounded-lg shadow-sm">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Edit Application - {{ $application->application_number }}</h3>
        </div>

        <form method="POST" action="{{ route('admissions.update', $application->id) }}" class="p-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Application Number (read only) -->
                <div class="md:col-span-2">
                    <label for="application_number" class="block text-sm font-medium text-gray-700 mb-1">
                        Application Number
                    </label>
                    <input
                        type="text"
                        id="application_number"
                        value="{{ $application->application_number }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600 cursor-not-allowed"
                        readonly
                        disabled
                    >
                    <p class="mt-1 text-sm text-gray-500">The application number is generated automatically and cannot be changed.</p>
                </div>

                <!-- Student Name -->
                <div>
                    <label for="student_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Student Name <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="student_name" 
                        id="student_name" 
                        value="{{ old('student_name', $application->student_name) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('student_name') border-red-500 @enderror"
                        required
                    >
                    @error('student_name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Father Name -->
                <div>
                    <label for="father_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Father Name <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="father_name" 
                        id="father_name" 
                        value="{{ old('father_name', $application->father_name) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('father_name') border-red-500 @enderror"
                        required
                    >
                    @error('father_name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Date of Birth -->
                <div>
                    <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">
                        Date of Birth
                    </label>
                    <input 
                        type="date" 
                        name="date_of_birth" 
                        id="date_of_birth" 
                        value="{{ old('date_of_birth', $application->date_of_birth?->format('Y-m-d')) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('date_of_birth') border-red-500 @enderror"
                    >
                    @error('date_of_birth')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Gender -->
                <div>
                    <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">
                        Gender <span class="text-red-500">*</span>
                    </label>
                    <select 
                        name="gender" 
                        id="gender"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('gender') border-red-500 @enderror"
                        required
                    >
                        <option value="">Select Gender</option>
                        <option value="Male" {{ old('gender', $application->gender) == 'Male' ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ old('gender', $application->gender) == 'Female' ? 'selected' : '' }}>Female</option>
                    </select>
                    @error('gender')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- B-Form Number -->
                <div>
                    <label for="b_form_number" class="block text-sm font-medium text-gray-700 mb-1">
                        B-Form Number
                    </label>
                    <input 
                        type="text" 
                        name="b_form_number" 
                        id="b_form_number" 
                        value="{{ old('b_form_number', $application->b_form_number) }}"
                        placeholder="XXXXX-XXXXXXX-X"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('b_form_number') border-red-500 @enderror"
                    >
                    @error('b_form_number')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Father Mobile -->
                <div>
                    <label for="father_mobile" class="block text-sm font-medium text-gray-700 mb-1">
                        Father Mobile <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="father_mobile" 
                        id="father_mobile" 
                        value="{{ old('father_mobile', $application->father_mobile) }}"
                        placeholder="03XX-XXXXXXX"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('father_mobile') border-red-500 @enderror"
                        required
                    >
                    @error('father_mobile')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Mother Mobile -->
                <div>
                    <label for="mother_mobile" class="block text-sm font-medium text-gray-700 mb-1">
                        Mother Mobile
                    </label>
                    <input 
                        type="text" 
                        name="mother_mobile" 
                        id="mother_mobile" 
                        value="{{ old('mother_mobile', $application->mother_mobile) }}"
                        placeholder="03XX-XXXXXXX"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('mother_mobile') border-red-500 @enderror"
                    >
                    @error('mother_mobile')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Student Type -->
                <div>
                    <label for="student_type" class="block text-sm font-medium text-gray-700 mb-1">
                        Student Type <span class="text-red-500">*</span>
                    </label>
                    <select 
                        name="student_type" 
                        id="student_type"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('student_type') border-red-500 @enderror"
                        required
                    >
                        <option value="">Select Student Type</option>
                        @foreach(\App\Models\AdmissionApplication::STUDENT_TYPES as $type)
                            <option value="{{ $type }}" {{ old('student_type', $application->student_type) === $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('student_type')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Admission Date -->
                <div>
                    <label for="admission_date" class="block text-sm font-medium text-gray-700 mb-1">
                        Admission Date
                    </label>
                    <input 
                        type="date" 
                        name="admission_date" 
                        id="admission_date" 
                        value="{{ old('admission_date', $application->admission_date?->format('Y-m-d')) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('admission_date') border-red-500 @enderror"
                    >
                    @error('admission_date')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                        Status <span class="text-red-500">*</span>
                    </label>
                    {{-- Only the transitions the workflow permits are offered.
                         The same rules are enforced server side. --}}
                    <select
                        name="status"
                        id="status"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('status') border-red-500 @enderror @if($application->isLocked()) bg-gray-100 text-gray-600 cursor-not-allowed @endif"
                        required
                        @disabled($application->isLocked())
                    >
                        @foreach($application->selectableStatuses() as $status)
                            <option value="{{ $status }}" {{ old('status', $application->status) === $status ? 'selected' : '' }}>{{ $status }}</option>
                        @endforeach
                    </select>
                    @if($application->isLocked())
                        {{-- Disabled inputs are not submitted, so keep the value. --}}
                        <input type="hidden" name="status" value="{{ $application->status }}">
                        <p class="mt-1 text-sm text-gray-500">
                            Locked: a student record has been created from this application.
                        </p>
                    @elseif(count($application->selectableStatuses()) === 1)
                        <p class="mt-1 text-sm text-gray-500">
                            @if($application->status === 'Passed')
                                Use the Approve Admission action on the application page to admit this student.
                            @elseif(in_array($application->status, ['Test Scheduled', 'Test Completed'], true))
                                Record the admission test result below to move this application to Passed or Failed.
                            @else
                                This status cannot be changed any further.
                            @endif
                        </p>
                    @endif
                    @error('status')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Academic Session -->
                <div>
                    <label for="academic_session_id" class="block text-sm font-medium text-gray-700 mb-1">
                        Academic Session
                    </label>
                    {{-- Correctable, unlike the status: the session carries no
                         workflow rules, and applications filed before it was
                         recorded have none at all, so somebody has to be able
                         to set one. Locked once a student exists, because the
                         student's enrollment has recorded a session by then
                         and the two must not be able to disagree. --}}
                    <select
                        name="academic_session_id"
                        id="academic_session_id"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('academic_session_id') border-red-500 @enderror @if($application->isLocked()) bg-gray-100 text-gray-600 cursor-not-allowed @endif"
                        @disabled($application->isLocked())
                    >
                        <option value="">Not Assigned</option>
                        @foreach($academicSessions as $session)
                            <option value="{{ $session->id }}" {{ (int) old('academic_session_id', $application->academic_session_id) === $session->id ? 'selected' : '' }}>
                                {{ $session->name }}{{ $session->is_current ? ' (Current)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @if($application->isLocked())
                        {{-- Disabled inputs are not submitted, so keep the value. --}}
                        <input type="hidden" name="academic_session_id" value="{{ $application->academic_session_id }}">
                        <p class="mt-1 text-sm text-gray-500">
                            Locked: a student record has been created from this application.
                        </p>
                    @else
                        <p class="mt-1 text-sm text-gray-500">
                            The year this application is for. Applications with no session do not appear on
                            session notices such as Passed Students.
                        </p>
                    @endif
                    @error('academic_session_id')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Permanent Address -->
                <div class="md:col-span-2">
                    <label for="permanent_address" class="block text-sm font-medium text-gray-700 mb-1">
                        Permanent Address
                    </label>
                    <textarea 
                        name="permanent_address" 
                        id="permanent_address" 
                        rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('permanent_address') border-red-500 @enderror"
                    >{{ old('permanent_address', $application->permanent_address) }}</textarea>
                    @error('permanent_address')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Current Address -->
                <div class="md:col-span-2">
                    <label for="current_address" class="block text-sm font-medium text-gray-700 mb-1">
                        Current Address
                    </label>
                    <textarea 
                        name="current_address" 
                        id="current_address" 
                        rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('current_address') border-red-500 @enderror"
                    >{{ old('current_address', $application->current_address) }}</textarea>
                    @error('current_address')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Notes -->
                <div class="md:col-span-2">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                        Notes
                    </label>
                    <textarea 
                        name="notes" 
                        id="notes" 
                        rows="4"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('notes') border-red-500 @enderror"
                        placeholder="Any additional information..."
                    >{{ old('notes', $application->notes) }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Admission Test -->
            <div class="mt-8 pt-6 border-t border-gray-200">
                <div class="mb-4">
                    <h4 class="text-base font-semibold text-gray-800">Admission Test</h4>
                    <p class="text-sm text-gray-600 mt-1">
                        Entering a date and time schedules the test, moving a Pending application
                        to Test Scheduled. Recording a result sets the application status to
                        Passed or Failed automatically.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Test Date -->
                    <div>
                        <label for="test_date" class="block text-sm font-medium text-gray-700 mb-1">
                            Test Date
                        </label>
                        <input
                            type="date"
                            name="test_date"
                            id="test_date"
                            value="{{ old('test_date', $application->test_date?->format('Y-m-d')) }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('test_date') border-red-500 @enderror"
                        >
                        @error('test_date')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Test Time -->
                    <div>
                        <label for="test_time" class="block text-sm font-medium text-gray-700 mb-1">
                            Test Time
                        </label>
                        <input
                            type="time"
                            name="test_time"
                            id="test_time"
                            value="{{ old('test_time', $application->testTimeForInput()) }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('test_time') border-red-500 @enderror"
                        >
                        @error('test_time')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Test Marks -->
                    <div>
                        <label for="test_marks" class="block text-sm font-medium text-gray-700 mb-1">
                            Test Marks
                        </label>
                        <input
                            type="number"
                            name="test_marks"
                            id="test_marks"
                            value="{{ old('test_marks', $application->test_marks) }}"
                            step="0.01"
                            min="0"
                            max="999.99"
                            placeholder="e.g. 85.50"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('test_marks') border-red-500 @enderror"
                        >
                        @error('test_marks')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Test Result -->
                    <div>
                        <label for="test_result" class="block text-sm font-medium text-gray-700 mb-1">
                            Test Result
                        </label>
                        <select
                            name="test_result"
                            id="test_result"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('test_result') border-red-500 @enderror"
                        >
                            <option value="">Not recorded yet</option>
                            @foreach(\App\Models\AdmissionApplication::TEST_RESULTS as $result)
                                <option value="{{ $result }}" {{ old('test_result', $application->test_result) === $result ? 'selected' : '' }}>{{ $result }}</option>
                            @endforeach
                        </select>
                        @error('test_result')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Test Remarks -->
                    <div class="md:col-span-2">
                        <label for="test_remarks" class="block text-sm font-medium text-gray-700 mb-1">
                            Test Remarks
                        </label>
                        <textarea
                            name="test_remarks"
                            id="test_remarks"
                            rows="3"
                            placeholder="Observations from the test..."
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('test_remarks') border-red-500 @enderror"
                        >{{ old('test_remarks', $application->test_remarks) }}</textarea>
                        @error('test_remarks')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3 mt-6 pt-6 border-t border-gray-200">
                <a 
                    href="{{ route('admissions.index') }}" 
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                >
                    Cancel
                </a>
                <button 
                    type="submit"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Update Application
                </button>
            </div>
        </form>
    </div>
</x-layout.admin>
