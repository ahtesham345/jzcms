<x-layout.admin title="Edit Teacher">
    <x-slot name="header">
        Edit Teacher
    </x-slot>

    <div class="bg-white rounded-lg shadow-sm">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Edit Teacher - {{ $teacher->teacher_id }}</h3>
        </div>

        <form method="POST" action="{{ route('teachers.update', $teacher->id) }}" class="p-6" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <!-- Personal Information -->
            <h4 class="text-base font-semibold text-gray-800 mb-4">Personal Information</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Photo -->
                <div class="md:col-span-2">
                    <label for="photo" class="block text-sm font-medium text-gray-700 mb-1">Photo</label>

                    <!-- Current photo preview -->
                    <div class="flex items-center mb-3">
                        <div class="h-20 w-20 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden border border-gray-300">
                            @if($teacher->hasPhoto())
                                <img src="{{ $teacher->photoUrl() }}" class="h-20 w-20 rounded-full object-cover" alt="{{ $teacher->full_name }}">
                            @else
                                <span class="text-xl font-medium text-gray-600">{{ $teacher->initial() }}</span>
                            @endif
                        </div>
                        <p class="ml-4 text-sm text-gray-500">
                            {{ $teacher->hasPhoto() ? 'Current photo. Uploading a new one replaces it.' : 'No photo uploaded yet.' }}
                        </p>
                    </div>

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

                <!-- Teacher ID (read only) -->
                <div>
                    <label for="teacher_id_display" class="block text-sm font-medium text-gray-700 mb-1">Teacher ID</label>
                    {{-- Not submitted: the field is disabled and the server
                         rejects a teacher_id in the request regardless. --}}
                    <input type="text" id="teacher_id_display" value="{{ $teacher->teacher_id }}" readonly disabled
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600 cursor-not-allowed">
                    <p class="mt-1 text-sm text-gray-500">The teacher ID is generated automatically and cannot be changed.</p>
                    @error('teacher_id')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Full Name -->
                <div>
                    <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="full_name" id="full_name" value="{{ old('full_name', $teacher->full_name) }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('full_name') border-red-500 @enderror">
                    @error('full_name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Father Name -->
                <div>
                    <label for="father_name" class="block text-sm font-medium text-gray-700 mb-1">Father Name</label>
                    <input type="text" name="father_name" id="father_name" value="{{ old('father_name', $teacher->father_name) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('father_name') border-red-500 @enderror">
                    @error('father_name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Date of Birth -->
                <div>
                    <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                    <input type="date" name="date_of_birth" id="date_of_birth" value="{{ old('date_of_birth', $teacher->date_of_birth?->format('Y-m-d')) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('date_of_birth') border-red-500 @enderror">
                    @error('date_of_birth')
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
                        @foreach(\App\Models\Teacher::GENDERS as $gender)
                            <option value="{{ $gender }}" {{ old('gender', $teacher->gender) === $gender ? 'selected' : '' }}>{{ $gender }}</option>
                        @endforeach
                    </select>
                    @error('gender')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- CNIC -->
                <div>
                    <label for="cnic_number" class="block text-sm font-medium text-gray-700 mb-1">CNIC Number</label>
                    <input type="text" name="cnic_number" id="cnic_number" value="{{ old('cnic_number', $teacher->cnic_number) }}" placeholder="XXXXX-XXXXXXX-X"
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
                    <input type="text" name="mobile_number" id="mobile_number" value="{{ old('mobile_number', $teacher->mobile_number) }}" placeholder="03XX-XXXXXXX" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('mobile_number') border-red-500 @enderror">
                    @error('mobile_number')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Alternate Mobile -->
                <div>
                    <label for="alternate_mobile" class="block text-sm font-medium text-gray-700 mb-1">Alternate Mobile</label>
                    <input type="text" name="alternate_mobile" id="alternate_mobile" value="{{ old('alternate_mobile', $teacher->alternate_mobile) }}" placeholder="03XX-XXXXXXX"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('alternate_mobile') border-red-500 @enderror">
                    @error('alternate_mobile')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email', $teacher->email) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-500 @enderror">
                    @error('email')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Address -->
                <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea name="address" id="address" rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('address') border-red-500 @enderror">{{ old('address', $teacher->address) }}</textarea>
                    @error('address')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Professional Information -->
            <h4 class="text-base font-semibold text-gray-800 mt-8 mb-4 pt-6 border-t border-gray-200">Professional Information</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Qualification -->
                <div>
                    <label for="qualification" class="block text-sm font-medium text-gray-700 mb-1">Qualification</label>
                    <input type="text" name="qualification" id="qualification" value="{{ old('qualification', $teacher->qualification) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('qualification') border-red-500 @enderror">
                    @error('qualification')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Specialization -->
                <div>
                    <label for="specialization" class="block text-sm font-medium text-gray-700 mb-1">Specialization</label>
                    <input type="text" name="specialization" id="specialization" value="{{ old('specialization', $teacher->specialization) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('specialization') border-red-500 @enderror">
                    @error('specialization')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Joining Date -->
                <div>
                    <label for="joining_date" class="block text-sm font-medium text-gray-700 mb-1">
                        Joining Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="joining_date" id="joining_date" value="{{ old('joining_date', $teacher->joining_date?->format('Y-m-d')) }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('joining_date') border-red-500 @enderror">
                    @error('joining_date')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Status -->
                <div>
                    <label for="teacher_status" class="block text-sm font-medium text-gray-700 mb-1">
                        Status <span class="text-red-500">*</span>
                    </label>
                    <select name="teacher_status" id="teacher_status" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('teacher_status') border-red-500 @enderror">
                        @foreach(\App\Models\Teacher::STATUSES as $statusOption)
                            <option value="{{ $statusOption }}" {{ old('teacher_status', $teacher->teacher_status) === $statusOption ? 'selected' : '' }}>{{ $statusOption }}</option>
                        @endforeach
                    </select>
                    @error('teacher_status')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Classes Taught -->
                @php
                    $assignedClassIds = old('academic_class_ids', $teacher->academicClasses->pluck('id')->all());
                @endphp
                <div class="md:col-span-2">
                    <label for="academic_class_ids" class="block text-sm font-medium text-gray-700 mb-1">
                        Classes Taught
                    </label>
                    @if($academicClasses->isEmpty())
                        <p class="text-sm text-gray-500">No active classes are available to assign.</p>
                    @else
                        <select
                            name="academic_class_ids[]"
                            id="academic_class_ids"
                            multiple
                            size="8"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('academic_class_ids') border-red-500 @enderror"
                        >
                            {{-- Grouped by department: the group label is the
                                 department, the option is the class. --}}
                            @foreach($academicClasses->groupBy(fn ($class) => $class->department?->name ?? 'Unassigned') as $departmentName => $classes)
                                <optgroup label="{{ $departmentName }}">
                                    @foreach($classes as $class)
                                        <option value="{{ $class->id }}" {{ in_array($class->id, $assignedClassIds) ? 'selected' : '' }}>
                                            {{ $class->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <p class="mt-1 text-sm text-gray-500">
                            Optional. Hold Ctrl (Cmd on Mac) to select more than one class. Deselecting removes the assignment.
                        </p>
                    @endif
                    @error('academic_class_ids')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                    @error('academic_class_ids.*')
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
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('notes') border-red-500 @enderror">{{ old('notes', $teacher->notes) }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3 mt-6 pt-6 border-t border-gray-200">
                <a href="{{ route('teachers.index') }}"
                   class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Update Teacher
                </button>
            </div>
        </form>
    </div>
</x-layout.admin>
