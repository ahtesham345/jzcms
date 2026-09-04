<x-layout.admin title="Parent Profile">
    <x-slot name="header">
        Parent Profile
    </x-slot>

    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <!-- Header -->
            <div class="px-6 py-6 bg-gradient-to-r from-blue-600 to-blue-700">
                <div class="flex flex-col sm:flex-row items-center sm:items-start space-y-4 sm:space-y-0 sm:space-x-6">
                    <!-- Photo -->
                    <div class="flex-shrink-0">
                        <div class="w-32 h-32 rounded-full bg-white/20 flex items-center justify-center border-4 border-white/30 overflow-hidden">
                            @if($parent->hasPhoto())
                                <img src="{{ $parent->photoUrl() }}" class="w-32 h-32 rounded-full object-cover" alt="{{ $parent->full_name }}">
                            @else
                                <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </div>
                    </div>

                    <!-- Basic Info -->
                    <div class="text-center sm:text-left">
                        <h2 class="text-2xl font-bold text-white">{{ $parent->full_name }}</h2>
                        <p class="text-blue-100 mt-1">Parent ID: {{ $parent->parent_id }}</p>
                        <div class="mt-3">
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $parent->statusBadgeClasses() }}">
                                {{ $parent->parent_status }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Details -->
            <div class="px-6 py-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Personal Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Personal Information</h3>

                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Parent ID</label>
                                <p class="text-gray-900">{{ $parent->parent_id }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Full Name</label>
                                <p class="text-gray-900">{{ $parent->full_name }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Father Name</label>
                                <p class="text-gray-900">{{ $parent->father_name ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Gender</label>
                                <p class="text-gray-900">{{ $parent->gender }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">CNIC Number</label>
                                <p class="text-gray-900">{{ $parent->cnic_number ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Contact Information</h3>

                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Mobile Number</label>
                                <p class="text-gray-900">{{ $parent->mobile_number }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Alternate Mobile</label>
                                <p class="text-gray-900">{{ $parent->alternate_mobile ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Email</label>
                                <p class="text-gray-900">{{ $parent->email ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Address</label>
                                <p class="text-gray-900 whitespace-pre-line">{{ $parent->address ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Professional Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Professional Information</h3>

                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Occupation</label>
                                <p class="text-gray-900">{{ $parent->occupation ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Additional Information</h3>

                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Status</label>
                                <p class="mt-1">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $parent->statusBadgeClasses() }}">
                                        {{ $parent->parent_status }}
                                    </span>
                                </p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Notes</label>
                                @if($parent->notes)
                                    <div class="mt-1 bg-gray-50 border border-gray-200 rounded-lg p-4">
                                        <p class="text-gray-900 whitespace-pre-line">{{ $parent->notes }}</p>
                                    </div>
                                @else
                                    <p class="text-gray-900">N/A</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Children / Students -->
                    <div class="space-y-4 lg:col-span-2" x-data="{ linking: {{ $errors->any() ? 'true' : 'false' }} }">
                        <div class="flex items-center justify-between border-b pb-2">
                            <h3 class="text-lg font-semibold text-gray-800">Children / Students</h3>
                            <button
                                type="button"
                                @click="linking = !linking"
                                class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Link Student
                            </button>
                        </div>

                        <!-- Link Student form -->
                        <div x-show="linking" x-cloak class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                            @if($linkableStudents->isEmpty())
                                <p class="text-sm text-gray-500">There are no other students available to link.</p>
                            @else
                                <form method="POST" action="{{ route('parents.students.store', $parent->id) }}" class="space-y-4">
                                    @csrf

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="md:col-span-2">
                                            <label for="student_id" class="block text-sm font-medium text-gray-700 mb-1">
                                                Student <span class="text-red-500">*</span>
                                            </label>
                                            <select name="student_id" id="student_id" required
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('student_id') border-red-500 @enderror">
                                                <option value="">Select Student</option>
                                                @foreach($linkableStudents as $student)
                                                    <option value="{{ $student->id }}" {{ (int) old('student_id') === $student->id ? 'selected' : '' }}>
                                                        {{ $student->registration_number }} — {{ $student->full_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('student_id')
                                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label for="relationship_type" class="block text-sm font-medium text-gray-700 mb-1">
                                                Relationship <span class="text-red-500">*</span>
                                            </label>
                                            <select name="relationship_type" id="relationship_type" required
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('relationship_type') border-red-500 @enderror">
                                                <option value="">Select Relationship</option>
                                                @foreach($relationshipTypes as $type)
                                                    <option value="{{ $type }}" {{ old('relationship_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                                                @endforeach
                                            </select>
                                            @error('relationship_type')
                                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div>
                                        <label for="is_primary" class="inline-flex items-center">
                                            <input type="hidden" name="is_primary" value="0">
                                            <input type="checkbox" name="is_primary" id="is_primary" value="1" {{ old('is_primary') ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2 text-sm text-gray-700">Primary contact for this relationship</span>
                                        </label>
                                        <p class="mt-1 text-sm text-gray-500">
                                            A student can have only one primary Father, one primary Mother and one primary Guardian.
                                        </p>
                                        @error('is_primary')
                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="flex items-center justify-end space-x-2">
                                        <button type="button" @click="linking = false"
                                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-white transition-colors">
                                            Cancel
                                        </button>
                                        <button type="submit"
                                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                            Link Student
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </div>

                        @if($parent->students->isEmpty())
                            <p class="text-gray-900">No students linked.</p>
                        @else
                            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registration No</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Relationship</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($parent->students as $student)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-10 w-10">
                                                            <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center overflow-hidden">
                                                                @if($student->photo && \Storage::disk('public')->exists($student->photo))
                                                                    <img src="{{ asset('storage/' . $student->photo) }}" class="h-10 w-10 rounded-full object-cover" alt="{{ $student->full_name }}">
                                                                @else
                                                                    <span class="text-sm font-medium text-gray-700">{{ mb_strtoupper(mb_substr($student->full_name, 0, 1)) }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="ml-3">
                                                            <a href="{{ route('students.show', $student->id) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                                                {{ $student->full_name }}
                                                            </a>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student->registration_number }}</td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student->student_type }}</td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student->department?->name ?? 'N/A' }}</td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student->academicClass?->name ?? 'N/A' }}</td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student->section?->name ?? 'Not assigned' }}</td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ $student->pivot->relationship_type }}
                                                    @if($student->pivot->is_primary)
                                                        <span class="ml-1 inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Primary</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                                    <form action="{{ route('parents.students.destroy', [$parent->id, $student->id]) }}" method="POST" class="inline"
                                                          onsubmit="return confirm('Unlink this student from this parent? Only the link is removed — the student record is kept.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-800">Unlink</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <div class="flex flex-col sm:flex-row justify-between items-center text-sm text-gray-600 space-y-3 sm:space-y-0">
                    <div class="space-y-1 text-center sm:text-left">
                        <p>Created: {{ $parent->created_at->format('d M, Y h:i A') }}</p>
                        <p>Last Updated: {{ $parent->updated_at->format('d M, Y h:i A') }}</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <a href="{{ route('parents.index') }}"
                           class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-white transition-colors">
                            Back to List
                        </a>
                        <a href="{{ route('parents.edit', $parent->id) }}"
                           class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Edit Parent
                        </a>
                        <form action="{{ route('parents.destroy', $parent->id) }}" method="POST" class="inline"
                              onsubmit="return confirm('Are you sure you want to delete this parent?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layout.admin>
