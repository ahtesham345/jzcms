<x-layout.admin title="Edit Student">
    <x-slot name="header">
        Edit Student
    </x-slot>

    <div class="max-w-4xl">
        <!-- Back Button -->
        <div class="mb-4">
            <a href="{{ route('students.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Students
            </a>
        </div>

        <!-- Form Card -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    Edit Student Information
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    Update the student details below.
                </p>
            </div>

            <form action="{{ route('students.update', $student->id) }}" method="POST" class="px-6 py-6 space-y-6" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <!-- Basic Information Section -->
                <div class="space-y-6">
                    <h4 class="text-md font-semibold text-gray-700 border-b pb-2">Basic Information</h4>

                    <!-- Photo Upload -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Student Photo
                        </label>
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0">
                                <div id="photo-preview" class="h-24 w-24 rounded-full bg-gray-300 flex items-center justify-center overflow-hidden">
                                    @if($student->photo && \Storage::disk('public')->exists($student->photo))
                                        <img src="{{ asset('storage/' . $student->photo) }}" class="h-24 w-24 rounded-full object-cover">
                                    @else
                                        <svg class="w-12 h-12 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                </div>
                            </div>
                            <div class="flex-1">
                                <input 
                                    type="file" 
                                    name="photo" 
                                    id="photo" 
                                    accept="image/jpeg,image/jpg,image/png"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 @error('photo') border-red-500 @enderror"
                                    onchange="previewPhoto(event)"
                                >
                                @error('photo')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">
                                    Allowed: JPEG, JPG, PNG. Max size: 2MB
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Registration Number -->
                        <div>
                            <label for="registration_number" class="block text-sm font-medium text-gray-700 mb-2">
                                Registration Number <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="registration_number" 
                                id="registration_number" 
                                value="{{ old('registration_number', $student->registration_number) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('registration_number') border-red-500 @enderror"
                                placeholder="Enter registration number"
                                autofocus
                            >
                            @error('registration_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Roll Number -->
                        <div>
                            <label for="roll_number" class="block text-sm font-medium text-gray-700 mb-2">
                                Roll Number
                            </label>
                            <input 
                                type="text" 
                                name="roll_number" 
                                id="roll_number" 
                                value="{{ old('roll_number', $student->roll_number) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('roll_number') border-red-500 @enderror"
                                placeholder="Enter roll number"
                            >
                            @error('roll_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Full Name -->
                        <div>
                            <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="full_name" 
                                id="full_name" 
                                value="{{ old('full_name', $student->full_name) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('full_name') border-red-500 @enderror"
                                placeholder="Enter full name"
                            >
                            @error('full_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Father Name -->
                        <div>
                            <label for="father_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Father Name <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="father_name" 
                                id="father_name" 
                                value="{{ old('father_name', $student->father_name) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('father_name') border-red-500 @enderror"
                                placeholder="Enter father name"
                            >
                            @error('father_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Date of Birth -->
                        <div>
                            <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-2">
                                Date of Birth
                            </label>
                            <input 
                                type="date" 
                                name="date_of_birth" 
                                id="date_of_birth" 
                                value="{{ old('date_of_birth', $student->date_of_birth?->format('Y-m-d')) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('date_of_birth') border-red-500 @enderror"
                            >
                            @error('date_of_birth')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Gender -->
                        <div>
                            <label for="gender" class="block text-sm font-medium text-gray-700 mb-2">
                                Gender <span class="text-red-500">*</span>
                            </label>
                            <select 
                                name="gender" 
                                id="gender"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('gender') border-red-500 @enderror"
                            >
                                <option value="">Select gender</option>
                                <option value="Male" {{ old('gender', $student->gender) == 'Male' ? 'selected' : '' }}>Male</option>
                                <option value="Female" {{ old('gender', $student->gender) == 'Female' ? 'selected' : '' }}>Female</option>
                            </select>
                            @error('gender')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- B-Form Number -->
                        <div>
                            <label for="b_form_number" class="block text-sm font-medium text-gray-700 mb-2">
                                B-Form Number
                            </label>
                            <input 
                                type="text" 
                                name="b_form_number" 
                                id="b_form_number" 
                                value="{{ old('b_form_number', $student->b_form_number) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('b_form_number') border-red-500 @enderror"
                                placeholder="Enter B-Form number"
                            >
                            @error('b_form_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Contact Information Section -->
                <div class="space-y-6">
                    <h4 class="text-md font-semibold text-gray-700 border-b pb-2">Contact Information</h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Father Mobile -->
                        <div>
                            <label for="father_mobile" class="block text-sm font-medium text-gray-700 mb-2">
                                Father Mobile <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="father_mobile" 
                                id="father_mobile" 
                                value="{{ old('father_mobile', $student->father_mobile) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('father_mobile') border-red-500 @enderror"
                                placeholder="Enter father mobile number"
                            >
                            @error('father_mobile')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Mother Mobile -->
                        <div>
                            <label for="mother_mobile" class="block text-sm font-medium text-gray-700 mb-2">
                                Mother Mobile
                            </label>
                            <input 
                                type="text" 
                                name="mother_mobile" 
                                id="mother_mobile" 
                                value="{{ old('mother_mobile', $student->mother_mobile) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('mother_mobile') border-red-500 @enderror"
                                placeholder="Enter mother mobile number"
                            >
                            @error('mother_mobile')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Emergency Contact -->
                        <div class="md:col-span-2">
                            <label for="emergency_contact" class="block text-sm font-medium text-gray-700 mb-2">
                                Emergency Contact <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="emergency_contact" 
                                id="emergency_contact" 
                                value="{{ old('emergency_contact', $student->emergency_contact) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('emergency_contact') border-red-500 @enderror"
                                placeholder="Enter emergency contact number"
                            >
                            @error('emergency_contact')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Permanent Address -->
                        <div class="md:col-span-2">
                            <label for="permanent_address" class="block text-sm font-medium text-gray-700 mb-2">
                                Permanent Address
                            </label>
                            <textarea 
                                name="permanent_address" 
                                id="permanent_address" 
                                rows="3"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('permanent_address') border-red-500 @enderror"
                                placeholder="Enter permanent address"
                            >{{ old('permanent_address', $student->permanent_address) }}</textarea>
                            @error('permanent_address')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Current Address -->
                        <div class="md:col-span-2">
                            <label for="current_address" class="block text-sm font-medium text-gray-700 mb-2">
                                Current Address
                            </label>
                            <textarea 
                                name="current_address" 
                                id="current_address" 
                                rows="3"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('current_address') border-red-500 @enderror"
                                placeholder="Enter current address"
                            >{{ old('current_address', $student->current_address) }}</textarea>
                            @error('current_address')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Academic Information Section -->
                <div class="space-y-6">
                    <h4 class="text-md font-semibold text-gray-700 border-b pb-2">Academic Information</h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Admission Date -->
                        <div>
                            <label for="admission_date" class="block text-sm font-medium text-gray-700 mb-2">
                                Admission Date <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="date" 
                                name="admission_date" 
                                id="admission_date" 
                                value="{{ old('admission_date', $student->admission_date->format('Y-m-d')) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('admission_date') border-red-500 @enderror"
                            >
                            @error('admission_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Academic Session -->
                        <div>
                            <label for="academic_session_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Academic Session <span class="text-red-500">*</span>
                            </label>
                            <select 
                                name="academic_session_id" 
                                id="academic_session_id"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('academic_session_id') border-red-500 @enderror"
                            >
                                <option value="">Select academic session</option>
                                @foreach($academicSessions as $session)
                                    <option value="{{ $session->id }}" {{ old('academic_session_id', $student->academic_session_id) == $session->id ? 'selected' : '' }}>
                                        {{ $session->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('academic_session_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <x-student-placement-fields
                            :student="$student"
                            :placement-by-track="$placementByTrack"
                            :student-types="$studentTypes"
                            :department-names-by-id="$departmentNamesById"
                            :department-ids-by-student-type="$departmentIdsByStudentType"
                            :classes-by-department="$classesByDepartment"
                            :sections-by-class="$sectionsByClass"
                            :computer-semesters="$computerSemesters"
                        />

                        <!-- Student Status -->
                        <div>
                            <label for="student_status" class="block text-sm font-medium text-gray-700 mb-2">
                                Student Status <span class="text-red-500">*</span>
                            </label>
                            <select 
                                name="student_status" 
                                id="student_status"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('student_status') border-red-500 @enderror"
                            >
                                <option value="">Select status</option>
                                <option value="Active" {{ old('student_status', $student->student_status) == 'Active' ? 'selected' : '' }}>Active</option>
                                <option value="Passed" {{ old('student_status', $student->student_status) == 'Passed' ? 'selected' : '' }}>Passed</option>
                                <option value="Left" {{ old('student_status', $student->student_status) == 'Left' ? 'selected' : '' }}>Left</option>
                            </select>
                            @error('student_status')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Resident Type -->
                        <div>
                            <label for="resident_type" class="block text-sm font-medium text-gray-700 mb-2">
                                Resident Type <span class="text-red-500">*</span>
                            </label>
                            <select 
                                name="resident_type" 
                                id="resident_type"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('resident_type') border-red-500 @enderror"
                            >
                                <option value="">Select resident type</option>
                                <option value="Local Resident" {{ old('resident_type', $student->resident_type) == 'Local Resident' ? 'selected' : '' }}>Local Resident</option>
                                <option value="Outside Resident" {{ old('resident_type', $student->resident_type) == 'Outside Resident' ? 'selected' : '' }}>Outside Resident</option>
                            </select>
                            @error('resident_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Leaving Reason (Only show when status is Left) -->
                <div class="space-y-6" id="leaving-reason-section" style="display: none;">
                    <h4 class="text-md font-semibold text-gray-700 border-b pb-2">Leaving Information</h4>

                    <div>
                        <label for="leaving_reason" class="block text-sm font-medium text-gray-700 mb-2">
                            Leaving Reason <span class="text-red-500" id="leaving-reason-required">*</span>
                        </label>
                        <textarea 
                            name="leaving_reason" 
                            id="leaving_reason" 
                            rows="3"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('leaving_reason') border-red-500 @enderror"
                            placeholder="Enter the reason for leaving"
                        >{{ old('leaving_reason', $student->leaving_reason) }}</textarea>
                        @error('leaving_reason')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Additional Information Section -->
                <div class="space-y-6">
                    <h4 class="text-md font-semibold text-gray-700 border-b pb-2">Additional Information</h4>

                    <div class="space-y-6">
                        <!-- Medical Information -->
                        <div>
                            <label for="medical_information" class="block text-sm font-medium text-gray-700 mb-2">
                                Medical Information
                            </label>
                            <textarea 
                                name="medical_information" 
                                id="medical_information" 
                                rows="3"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('medical_information') border-red-500 @enderror"
                                placeholder="Enter any medical conditions or allergies"
                            >{{ old('medical_information', $student->medical_information) }}</textarea>
                            @error('medical_information')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Notes -->
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                Notes
                            </label>
                            <textarea 
                                name="notes" 
                                id="notes" 
                                rows="3"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('notes') border-red-500 @enderror"
                                placeholder="Enter any additional notes"
                            >{{ old('notes', $student->notes) }}</textarea>
                            @error('notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                    <a 
                        href="{{ route('students.index') }}" 
                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                        Cancel
                    </a>
                    <button 
                        type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        Update Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layout.admin>

<script>
function previewPhoto(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('photo-preview');
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" class="h-24 w-24 rounded-full object-cover">';
        }
        reader.readAsDataURL(file);
    }
}

// Toggle leaving reason section based on student status
document.addEventListener('DOMContentLoaded', function() {
    const studentStatus = document.getElementById('student_status');
    const leavingReasonSection = document.getElementById('leaving-reason-section');
    
    function toggleLeavingReason() {
        if (studentStatus.value === 'Left') {
            leavingReasonSection.style.display = 'block';
        } else {
            leavingReasonSection.style.display = 'none';
        }
    }
    
    // Check on page load
    toggleLeavingReason();
    
    // Check on change
    studentStatus.addEventListener('change', toggleLeavingReason);
});
</script>
