<x-layout.admin title="Add Parent">
    <x-slot name="header">
        Add Parent
    </x-slot>

    <div class="bg-white rounded-lg shadow-sm">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Parent Details</h3>
        </div>

        <form method="POST" action="{{ route('parents.store') }}" class="p-6" enctype="multipart/form-data">
            @csrf

            <!-- Personal Information -->
            <h4 class="text-base font-semibold text-gray-800 mb-4">Personal Information</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Photo -->
                <div class="md:col-span-2">
                    <label for="photo" class="block text-sm font-medium text-gray-700 mb-1">Photo</label>
                    <input
                        type="file"
                        name="photo"
                        id="photo"
                        accept="image/jpeg,image/png"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 @error('photo') border-red-500 @enderror"
                    >
                    <p class="mt-1 text-sm text-gray-500">Optional. JPG, JPEG or PNG, up to 2MB.</p>
                    @error('photo')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Parent ID (generated on save) -->
                <div>
                    <label for="parent_id_display" class="block text-sm font-medium text-gray-700 mb-1">Parent ID</label>
                    {{-- Preview of the next available ID, read from the same
                         ParentGuardian::nextParentId() the backend uses. Disabled
                         and not submitted: the final ID is allocated on save. --}}
                    <input type="text" id="parent_id_display" value="{{ $nextParentId }}" readonly disabled
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600 cursor-not-allowed">
                </div>

                <!-- Full Name -->
                <div>
                    <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="full_name" id="full_name" value="{{ old('full_name') }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('full_name') border-red-500 @enderror">
                    @error('full_name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Father Name -->
                <div>
                    <label for="father_name" class="block text-sm font-medium text-gray-700 mb-1">Father Name</label>
                    <input type="text" name="father_name" id="father_name" value="{{ old('father_name') }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('father_name') border-red-500 @enderror">
                    @error('father_name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Gender -->
                <div>
                    <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">
                        Gender <span class="text-red-500">*</span>
                    </label>
                    <select name="gender" id="gender" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('gender') border-red-500 @enderror">
                        <option value="">Select Gender</option>
                        @foreach(\App\Models\ParentGuardian::GENDERS as $gender)
                            <option value="{{ $gender }}" {{ old('gender') === $gender ? 'selected' : '' }}>{{ $gender }}</option>
                        @endforeach
                    </select>
                    @error('gender')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- CNIC -->
                <div>
                    <label for="cnic_number" class="block text-sm font-medium text-gray-700 mb-1">CNIC Number</label>
                    <input type="text" name="cnic_number" id="cnic_number" value="{{ old('cnic_number') }}" placeholder="XXXXX-XXXXXXX-X"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('cnic_number') border-red-500 @enderror">
                    @error('cnic_number')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Contact Information -->
            <h4 class="text-base font-semibold text-gray-800 mt-8 mb-4 pt-6 border-t border-gray-200">Contact Information</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Mobile -->
                <div>
                    <label for="mobile_number" class="block text-sm font-medium text-gray-700 mb-1">
                        Mobile Number <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="mobile_number" id="mobile_number" value="{{ old('mobile_number') }}" placeholder="03XX-XXXXXXX" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('mobile_number') border-red-500 @enderror">
                    @error('mobile_number')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Alternate Mobile -->
                <div>
                    <label for="alternate_mobile" class="block text-sm font-medium text-gray-700 mb-1">Alternate Mobile</label>
                    <input type="text" name="alternate_mobile" id="alternate_mobile" value="{{ old('alternate_mobile') }}" placeholder="03XX-XXXXXXX"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('alternate_mobile') border-red-500 @enderror">
                    @error('alternate_mobile')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-500 @enderror">
                    @error('email')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Address -->
                <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea name="address" id="address" rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('address') border-red-500 @enderror">{{ old('address') }}</textarea>
                    @error('address')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Professional Information -->
            <h4 class="text-base font-semibold text-gray-800 mt-8 mb-4 pt-6 border-t border-gray-200">Professional Information</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Occupation -->
                <div>
                    <label for="occupation" class="block text-sm font-medium text-gray-700 mb-1">Occupation</label>
                    <input type="text" name="occupation" id="occupation" value="{{ old('occupation') }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('occupation') border-red-500 @enderror">
                    @error('occupation')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Status -->
                <div>
                    <label for="parent_status" class="block text-sm font-medium text-gray-700 mb-1">
                        Status <span class="text-red-500">*</span>
                    </label>
                    <select name="parent_status" id="parent_status" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('parent_status') border-red-500 @enderror">
                        @foreach(\App\Models\ParentGuardian::STATUSES as $statusOption)
                            <option value="{{ $statusOption }}" {{ old('parent_status', 'Active') === $statusOption ? 'selected' : '' }}>{{ $statusOption }}</option>
                        @endforeach
                    </select>
                    @error('parent_status')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Additional Information -->
            <h4 class="text-base font-semibold text-gray-800 mt-8 mb-4 pt-6 border-t border-gray-200">Additional Information</h4>
            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" id="notes" rows="4" placeholder="Any additional information..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('notes') border-red-500 @enderror">{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3 mt-6 pt-6 border-t border-gray-200">
                <a href="{{ route('parents.index') }}"
                   class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Create Parent
                </button>
            </div>
        </form>
    </div>
</x-layout.admin>
