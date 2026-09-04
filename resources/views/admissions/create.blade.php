<x-layout.admin title="Add Admission Application">
    <x-slot name="header">
        Add Admission Application
    </x-slot>

    <div class="bg-white rounded-lg shadow-sm">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Application Details</h3>
        </div>

        <form method="POST" action="{{ route('admissions.store') }}" class="p-6">
            @csrf

            {{-- Stamped server side on save, never posted from this form.
                 Shown so the admin can see which year the application will be
                 filed into before they fill it in. --}}
            @if($academicSession)
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-sm text-blue-900">
                        <span class="font-medium">Academic Session:</span>
                        <span class="font-semibold">{{ $academicSession->name }}</span>
                    </p>
                    <p class="text-xs text-blue-800 mt-1">
                        Applications are filed under the current academic session. It can be corrected
                        afterwards from the edit page.
                    </p>
                </div>
            @else
                <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                    <p class="text-sm font-medium text-amber-900">No current academic session is set.</p>
                    <p class="text-xs text-amber-800 mt-1">
                        This application will be saved without a session and will not appear on session
                        notices such as Passed Students. Set a current session in Academic Sessions, or
                        assign one from this application's edit page afterwards.
                    </p>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Student Name -->
                <div>
                    <label for="student_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Student Name <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="student_name" 
                        id="student_name" 
                        value="{{ old('student_name') }}"
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
                        value="{{ old('father_name') }}"
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
                        value="{{ old('date_of_birth') }}"
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
                        <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
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
                        value="{{ old('b_form_number') }}"
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
                        value="{{ old('father_mobile') }}"
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
                        value="{{ old('mother_mobile') }}"
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
                            <option value="{{ $type }}" {{ old('student_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
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
                        value="{{ old('admission_date') }}"
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
                    <select 
                        name="status" 
                        id="status"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('status') border-red-500 @enderror"
                        required
                    >
                        {{-- Passed/Failed need a test result and Approved needs a
                             student, so neither can be chosen at creation. --}}
                        @foreach(\App\Models\AdmissionApplication::creatableStatuses() as $status)
                            <option value="{{ $status }}" {{ old('status', 'Pending') === $status ? 'selected' : '' }}>{{ $status }}</option>
                        @endforeach
                    </select>
                    @error('status')
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
                    >{{ old('permanent_address') }}</textarea>
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
                    >{{ old('current_address') }}</textarea>
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
                    >{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
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
                    Create Application
                </button>
            </div>
        </form>
    </div>
</x-layout.admin>
