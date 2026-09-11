<x-layout.admin title="View Student">
    <x-slot name="header">
        Student Profile
    </x-slot>

    <div class="max-w-5xl">
        <!-- Back Button -->
        <div class="mb-4">
            <a href="{{ route('students.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Students
            </a>
        </div>

        <!-- Student Profile Card -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <!-- Header with Photo and Basic Info -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-8">
                <div class="flex flex-col md:flex-row items-center md:items-start space-y-4 md:space-y-0 md:space-x-6">
                    <!-- Photo Placeholder -->
                    <div class="flex-shrink-0">
                        <div class="w-32 h-32 rounded-full bg-white/20 flex items-center justify-center border-4 border-white/30 overflow-hidden">
                            @if($student->photo && \Storage::disk('public')->exists($student->photo))
                                <img src="{{ asset('storage/' . $student->photo) }}" class="w-32 h-32 rounded-full object-cover" alt="{{ $student->full_name }}">
                            @else
                                <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </div>
                    </div>

                    <!-- Basic Info -->
                    <div class="flex-1 text-center md:text-left">
                        <h2 class="text-3xl font-bold text-white mb-2">{{ $student->full_name }}</h2>
                        <div class="space-y-1">
                            <p class="text-blue-100">
                                <span class="font-semibold">Registration No:</span> {{ $student->registration_number }}
                            </p>
                            @if($student->roll_number)
                                <p class="text-blue-100">
                                    <span class="font-semibold">Roll No:</span> {{ $student->roll_number }}
                                </p>
                            @endif
                            <div class="flex flex-wrap gap-2 justify-center md:justify-start mt-3">
                                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full 
                                    {{ $student->student_status == 'Active' ? 'bg-green-100 text-green-800' : 
                                       ($student->student_status == 'Passed' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800') }}">
                                    {{ $student->student_status }}
                                </span>
                                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-white/20 text-white">
                                    {{ $student->student_type }}
                                </span>
                                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-white/20 text-white">
                                    {{ $student->gender }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col space-y-2">
                        <a 
                            href="{{ route('students.edit', $student->id) }}" 
                            class="inline-flex items-center justify-center px-4 py-2 bg-white text-blue-600 rounded-lg hover:bg-blue-50 transition-colors"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit Student
                        </a>
                        @if($student->canBePromoted())
                            <a
                                href="{{ route('students.promote', $student->id) }}"
                                class="inline-flex items-center justify-center px-4 py-2 bg-white text-green-700 rounded-lg hover:bg-green-50 transition-colors"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                </svg>
                                Promote Student
                            </a>
                        @endif
                        <form action="{{ route('students.destroy', $student->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this student?');">
                            @csrf
                            @method('DELETE')
                            <button 
                                type="submit" 
                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Delete
                            </button>
                        </form>
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
                                <label class="text-sm font-medium text-gray-500">Full Name</label>
                                <p class="text-gray-900">{{ $student->full_name }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-500">Father Name</label>
                                <p class="text-gray-900">{{ $student->father_name }}</p>
                            </div>

                            @if($student->date_of_birth)
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Date of Birth</label>
                                    <p class="text-gray-900">{{ $student->date_of_birth->format('d M, Y') }}</p>
                                </div>
                            @endif

                            <div>
                                <label class="text-sm font-medium text-gray-500">Gender</label>
                                <p class="text-gray-900">{{ $student->gender }}</p>
                            </div>

                            @if($student->b_form_number)
                                <div>
                                    <label class="text-sm font-medium text-gray-500">B-Form Number</label>
                                    <p class="text-gray-900">{{ $student->b_form_number }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Contact Information</h3>
                        
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Father Mobile</label>
                                <p class="text-gray-900">{{ $student->father_mobile }}</p>
                            </div>

                            @if($student->mother_mobile)
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Mother Mobile</label>
                                    <p class="text-gray-900">{{ $student->mother_mobile }}</p>
                                </div>
                            @endif

                            <div>
                                <label class="text-sm font-medium text-gray-500">Emergency Contact</label>
                                <p class="text-gray-900">{{ $student->emergency_contact }}</p>
                            </div>

                            @if($student->permanent_address)
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Permanent Address</label>
                                    <p class="text-gray-900">{{ $student->permanent_address }}</p>
                                </div>
                            @endif

                            @if($student->current_address)
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Current Address</label>
                                    <p class="text-gray-900">{{ $student->current_address }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Admission Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Admission Information</h3>
                        
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Admission Date</label>
                                <p class="text-gray-900">{{ $student->admission_date->format('d M, Y') }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-500">Student Status</label>
                                <p class="text-gray-900">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                        {{ $student->student_status == 'Active' ? 'bg-green-100 text-green-800' : 
                                           ($student->student_status == 'Passed' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800') }}">
                                        {{ $student->student_status }}
                                    </span>
                                </p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-500">Student Type</label>
                                <p class="text-gray-900">{{ $student->student_type }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-500">Resident Type</label>
                                <p class="text-gray-900">{{ $student->resident_type }}</p>
                            </div>
                        </div>
                    </div>

                    @if($student->student_status == 'Left' && $student->leaving_reason)
                        <!-- Leaving Reason -->
                        <div class="space-y-4 lg:col-span-2">
                            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Leaving Information</h3>
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                <label class="text-sm font-medium text-gray-500">Leaving Reason</label>
                                <p class="text-gray-900 mt-1">{{ $student->leaving_reason }}</p>
                            </div>
                        </div>
                    @endif

                    <!-- Academic Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Academic Information</h3>
                        
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Academic Session</label>
                                <p class="text-gray-900">{{ $student->academicSession->name }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-500">Department</label>
                                <p class="text-gray-900">{{ $student->department->name }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-500">Class</label>
                                <p class="text-gray-900">{{ $student->academicClass->name }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-500">Section</label>
                                <p class="text-gray-900">{{ $student->section?->name ?? 'Not assigned' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Current Academic Enrollment -->
                    @php
                        $enrollments = $student->academicEnrollments->sortByDesc('start_date');
                        $currentEnrollments = $enrollments->where('status', 'Active');
                    @endphp

                    <div class="space-y-4 lg:col-span-2" id="current-enrollment">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Current Academic Enrollment</h3>

                        @if($currentEnrollments->isEmpty())
                            <p class="text-gray-900">No active enrollment.</p>
                        @else
                            {{-- One card per active enrollment: a Hifz + School
                                 student holds one per track. --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($currentEnrollments as $current)
                                    <div class="border border-green-200 bg-green-50 rounded-lg p-4 space-y-3">
                                        <div class="flex items-center justify-between">
                                            <h4 class="text-base font-semibold text-gray-800">{{ $current->academic_track }}</h4>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $current->statusBadgeClasses() }}">
                                                {{ $current->status }}
                                            </span>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-gray-500">Academic Session</label>
                                            <p class="text-gray-900">{{ $current->academicSession?->name ?? 'N/A' }}</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-gray-500">Department</label>
                                            <p class="text-gray-900">{{ $current->department?->name ?? 'N/A' }}</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-gray-500">Class</label>
                                            <p class="text-gray-900">{{ $current->academicClass?->name ?? 'N/A' }}</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-gray-500">Section</label>
                                            <p class="text-gray-900">{{ $current->section?->name ?? 'No section' }}</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-gray-500">Enrollment Date</label>
                                            <p class="text-gray-900">{{ $current->start_date?->format('d M, Y') ?? 'N/A' }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Academic History -->
                    <div class="space-y-4 lg:col-span-2" id="academic-history" x-data="{ adding: {{ $errors->any() ? 'true' : 'false' }} }">
                        <div class="flex items-center justify-between border-b pb-2">
                            <h3 class="text-lg font-semibold text-gray-800">Academic History</h3>
                            <button
                                type="button"
                                @click="adding = ! adding"
                                class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Add Enrollment
                            </button>
                        </div>

                        <!-- Add Enrollment form -->
                        <div x-show="adding" x-cloak class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                            @if($academicSessions->isEmpty() || $departments->isEmpty())
                                <p class="text-sm text-gray-500">
                                    An active academic session and department are needed before an enrollment can be recorded.
                                </p>
                            @else
                                <x-enrollment-form
                                    :action="route('students.enrollments.store', $student->id)"
                                    :academic-sessions="$academicSessions"
                                    :departments="$departments"
                                    :classes-by-department="$classesByDepartment"
                                    :sections-by-class="$sectionsByClass"
                                    :academic-tracks="$academicTracks"
                                    :enrollment-statuses="$enrollmentStatuses"
                                    :cancel-url="route('students.show', $student->id)"
                                    submit-label="Add Enrollment"
                                />
                            @endif
                        </div>

                        @if($enrollments->isEmpty())
                            <p class="text-gray-900">No academic enrollment records for this student.</p>
                        @else
                            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Session</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Track</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enrolled</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        {{-- Newest first, so the current placement leads. --}}
                                        @foreach($enrollments as $enrollment)
                                            <tr class="hover:bg-gray-50 {{ $enrollment->isActive() ? 'bg-green-50' : '' }}">
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->academicSession?->name ?? 'N/A' }}</td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->academic_track }}</td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->department?->name ?? 'N/A' }}</td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->academicClass?->name ?? 'N/A' }}</td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->section?->name ?? 'No section' }}</td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->start_date?->format('d M, Y') ?? 'N/A' }}</td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->end_date?->format('d M, Y') ?? '—' }}</td>
                                                <td class="px-4 py-4 whitespace-nowrap">
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $enrollment->statusBadgeClasses() }}">
                                                        {{ $enrollment->status }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-4 text-sm text-gray-900">
                                                    @if($enrollment->notes)
                                                        <span title="{{ $enrollment->notes }}">{{ Str::limit($enrollment->notes, 40) }}</span>
                                                    @else
                                                        <span class="text-gray-400">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                                    <a href="{{ route('students.enrollments.edit', [$student->id, $enrollment->id]) }}" class="text-yellow-600 hover:text-yellow-800">
                                                        Edit
                                                    </a>
                                                    <form action="{{ route('students.enrollments.destroy', [$student->id, $enrollment->id]) }}" method="POST" class="inline"
                                                          onsubmit="return confirm('Delete this enrollment record? The academic history for this session will be lost.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-800">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <!-- Parents / Guardians -->
                    <div class="space-y-4 lg:col-span-2" x-data="{ linking: {{ $errors->any() ? 'true' : 'false' }} }">
                        <div class="flex items-center justify-between border-b pb-2">
                            <h3 class="text-lg font-semibold text-gray-800">Parents / Guardians</h3>
                            <button
                                type="button"
                                @click="linking = !linking"
                                class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Link Parent
                            </button>
                        </div>

                        <!-- Link Parent form -->
                        <div x-show="linking" x-cloak class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                            @if($linkableParents->isEmpty())
                                <p class="text-sm text-gray-500">There are no other parents available to link.</p>
                            @else
                                <form method="POST" action="{{ route('students.parents.store', $student->id) }}" class="space-y-4">
                                    @csrf

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="md:col-span-2">
                                            <label for="parent_id" class="block text-sm font-medium text-gray-700 mb-1">
                                                Parent / Guardian <span class="text-red-500">*</span>
                                            </label>
                                            <select name="parent_id" id="parent_id" required
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('parent_id') border-red-500 @enderror">
                                                <option value="">Select Parent</option>
                                                @foreach($linkableParents as $linkableParent)
                                                    <option value="{{ $linkableParent->id }}" {{ (int) old('parent_id') === $linkableParent->id ? 'selected' : '' }}>
                                                        {{ $linkableParent->parent_id }} — {{ $linkableParent->full_name }} ({{ $linkableParent->mobile_number }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('parent_id')
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
                                            Link Parent
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </div>

                        @if($student->parents->isEmpty())
                            <p class="text-gray-900">No parents or guardians linked.</p>
                        @else
                            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parent</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parent ID</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mobile</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Relationship</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($student->parents as $linkedParent)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-10 w-10">
                                                            <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center overflow-hidden">
                                                                @if($linkedParent->hasPhoto())
                                                                    <img src="{{ $linkedParent->photoUrl() }}" class="h-10 w-10 rounded-full object-cover" alt="{{ $linkedParent->full_name }}">
                                                                @else
                                                                    <span class="text-sm font-medium text-gray-700">{{ $linkedParent->initial() }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="ml-3">
                                                            <a href="{{ route('parents.show', $linkedParent->id) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                                                {{ $linkedParent->full_name }}
                                                            </a>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $linkedParent->parent_id }}</td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $linkedParent->mobile_number }}</td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ $linkedParent->pivot->relationship_type }}
                                                    @if($linkedParent->pivot->is_primary)
                                                        <span class="ml-1 inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Primary</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                                    <form action="{{ route('students.parents.destroy', [$student->id, $linkedParent->id]) }}" method="POST" class="inline"
                                                          onsubmit="return confirm('Unlink this parent from this student? Only the link is removed — the parent record is kept.');">
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

                    <!-- Attendance -->
                    <div class="space-y-4 lg:col-span-2" id="attendance">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Attendance</h3>

                        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Attendance is entered from the paper register on the monthly attendance sheet.
                                    The history below opens on its own page so this profile stays readable.
                                </p>
                                @if($currentEnrollments->count() > 1)
                                    <p class="text-sm text-gray-500 mt-1">
                                        This student is enrolled on both tracks. Each track keeps its own attendance.
                                    </p>
                                @endif
                            </div>

                            <div class="flex flex-wrap gap-2">
                                {{-- One link per active track when there are
                                     two, so a dual-track student lands on the
                                     history they meant to open. --}}
                                @if($currentEnrollments->count() > 1)
                                    @foreach($currentEnrollments as $current)
                                        <a href="{{ route('students.attendance', ['student' => $student->id, 'academic_track' => $current->academic_track]) }}"
                                           class="inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                                            {{ $current->academic_track }} Attendance
                                        </a>
                                    @endforeach
                                @endif

                                <a href="{{ route('students.attendance', $student->id) }}"
                                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                    </svg>
                                    View Attendance History
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Prayer Attendance -->
                    @php
                        // Any Madrassa enrollment, not only the current one:
                        // a student who has been promoted or has left still
                        // has a prayer history worth reading. A school-only
                        // student has none, and gets no section at all
                        // rather than one implying school prayers exist.
                        $madrassaPrayerEnrollment = $enrollments
                            ->firstWhere('academic_track', \App\Models\StudentPrayerAttendance::ACADEMIC_TRACK);
                    @endphp

                    @if($madrassaPrayerEnrollment)
                        <div class="space-y-4 lg:col-span-2" id="prayer-attendance">
                            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Prayer Attendance</h3>

                            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Fajr, Zuhr, Asr, Maghrib and Isha, as transcribed from the madrassa's paper
                                        prayer register. Separate from Attendance, which records whether the student
                                        was in class.
                                    </p>
                                    {{-- A Hifz + School student holds a school
                                         enrollment too. Only the madrassa side
                                         has a prayer register. --}}
                                    @if($currentEnrollments->count() > 1)
                                        <p class="text-sm text-gray-500 mt-1">
                                            This student is enrolled on both tracks. Only the Madrassa enrollment
                                            keeps a prayer register.
                                        </p>
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('students.prayer-attendance', $student->id) }}"
                                       class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                                        </svg>
                                        View Prayer Attendance
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Hifz & Quran / Daily Academic Record -->
                    {{-- The summary is resolved in the controller and is null
                         unless the student has a current madrassa placement on
                         a programme this module keeps a record for. A
                         school-only student gets no section at all rather than
                         one that leads nowhere.

                         A small summary and a link, deliberately: the history
                         itself is a page of its own so this profile stays
                         readable. --}}
                    @if($madrassaDailyRecordSummary)
                        @php($madrassaEnrollment = $madrassaDailyRecordSummary['enrollment'])

                        <div class="space-y-4 lg:col-span-2" id="hifz-quran">
                            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Hifz &amp; Quran / Daily Academic Record</h3>

                            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Total Records</label>
                                        <p class="text-2xl font-bold text-gray-900">{{ $madrassaDailyRecordSummary['total'] }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Latest Record Date</label>
                                        <p class="text-2xl font-bold text-gray-900">
                                            {{ $madrassaDailyRecordSummary['latest_date']?->format('d M, Y') ?? 'None' }}
                                        </p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Current Program</label>
                                        <p class="text-2xl font-bold text-gray-900">{{ $madrassaDailyRecordSummary['record_type'] }}</p>
                                    </div>
                                </div>

                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-t border-gray-200 pt-4">
                                    <div>
                                        <p class="text-sm text-gray-700">
                                            What this student did each day. Separate from attendance, which records only
                                            whether the student was present.
                                        </p>
                                        {{-- A Hifz + School student holds a school
                                             enrollment too. Only the madrassa side
                                             has a daily record, and these links
                                             open that side. --}}
                                        @if($currentEnrollments->count() > 1)
                                            <p class="text-sm text-gray-500 mt-1">
                                                This student is enrolled on both tracks. Only the
                                                {{ $madrassaEnrollment->academic_track }} enrollment
                                                ({{ $madrassaEnrollment->academicClass?->name ?? 'N/A' }}) is recorded here.
                                            </p>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('hifz.create', ['student_academic_enrollment_id' => $madrassaEnrollment->id]) }}"
                                           class="inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                                            Add Daily Record
                                        </a>

                                        <a href="{{ route('students.hifz', $student->id) }}"
                                           class="inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                            </svg>
                                            View Daily Records
                                        </a>

                                        {{-- Only rendered because the summary
                                             above exists, which already means
                                             this student has a madrassa
                                             placement this module covers. --}}
                                        <a href="{{ route('students.hifz.progress', $student->id) }}"
                                           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                            </svg>
                                            View {{ $madrassaDailyRecordSummary['record_type'] }} Progress
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Results -->
                    {{-- The summary is resolved in the controller and is null
                         unless the student has held a Madrassa enrollment. A
                         school-only student gets no section at all rather
                         than one that leads nowhere.

                         Two terms and their Grand Test, deliberately: the
                         full result history and the report are a later
                         chunk's job. --}}
                    @if($madrassaResultSummary)
                        @php($resultEnrollment = $madrassaResultSummary['enrollment'])

                        <div class="space-y-4 lg:col-span-2" id="results">
                            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Results</h3>

                            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 space-y-4">
                                <p class="text-sm text-gray-700">
                                    {{ $madrassaResultSummary['test_type'] }} results for the
                                    <span class="font-medium">{{ $resultEnrollment->academicSession?->name ?? 'current' }}</span>
                                    session, recorded against this student's
                                    <span class="font-medium">{{ $resultEnrollment->academic_track }}</span> enrollment
                                    ({{ $resultEnrollment->academicClass?->name ?? 'N/A' }}@if($resultEnrollment->section) &middot; {{ $resultEnrollment->section->name }}@endif).
                                </p>

                                {{-- A Hifz + School student holds a school
                                     enrollment too. Only the madrassa side
                                     carries a result. --}}
                                @if($currentEnrollments->count() > 1)
                                    <p class="text-sm text-gray-500">
                                        This student is enrolled on both tracks. Only the
                                        {{ $resultEnrollment->academic_track }} enrollment carries results.
                                    </p>
                                @endif

                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-100">
                                            <tr>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Term</th>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Marks</th>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obtained Marks</th>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            {{-- Both terms are always listed, so a
                                                 term nobody has marked reads as
                                                 "Not Entered" rather than going
                                                 missing from the table. --}}
                                            @foreach($madrassaResultSummary['terms'] as $termName => $termResult)
                                                <tr>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        {{ $termName }} {{ $madrassaResultSummary['test_type'] }}
                                                    </td>
                                                    @if($termResult)
                                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format((float) $termResult->total_marks, 2) }}</td>
                                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format((float) $termResult->obtained_marks, 2) }}</td>
                                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $termResult->formattedPercentage() }}</td>
                                                        <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $termResult->gradeBadgeClasses() }}">
                                                                {{ $termResult->grade }}
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                                            <a href="{{ route('results.show', $termResult->id) }}" class="text-blue-600 hover:text-blue-800">
                                                                View Result
                                                            </a>
                                                        </td>
                                                    @else
                                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500" colspan="4">
                                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">
                                                                Not Entered
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                                            @if($resultEnrollment->status === 'Active')
                                                                <a href="{{ route('results.create', [
                                                                        'student_academic_enrollment_id' => $resultEnrollment->id,
                                                                        'term' => $termName,
                                                                   ]) }}"
                                                                   class="text-blue-600 hover:text-blue-800">
                                                                    Add Result
                                                                </a>
                                                            @else
                                                                <span class="text-gray-400">—</span>
                                                            @endif
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="flex flex-wrap gap-2 border-t border-gray-200 pt-4">
                                    {{-- The full history, across every
                                         Madrassa placement this student has
                                         held. The two terms above are the
                                         current placement's; this is
                                         everything. --}}
                                    <a href="{{ route('students.results', $student->id) }}"
                                       class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        View All Results
                                    </a>

                                    <a href="{{ route('results.index', ['student_id' => $student->id]) }}"
                                       class="inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                                        Open in Results
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Discipline -->
                    {{-- Shown for every student, unlike the Madrassa
                         sections above. Discipline applies to the whole
                         school, and "no incidents" is a fact worth stating
                         rather than a reason to leave the panel out: the
                         status below is what says it.

                         The counts are resolved by one aggregate query in
                         the controller. Nothing here loads the student's
                         records or counts them in Blade. --}}
                    <div class="space-y-4 lg:col-span-2" id="discipline">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Discipline</h3>

                        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="text-sm text-gray-700">
                                    Incidents recorded against this student. A record belongs to the student, so it
                                    stays on file after a promotion or a change of class.
                                </p>

                                {{-- Derived on every read from the records
                                     themselves and never stored, so it can
                                     never disagree with them. --}}
                                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ \App\Models\DisciplineRecord::badgeClassesForStatus($disciplineSummary['status']) }}">
                                    {{ $disciplineSummary['status'] }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                                <div class="bg-white rounded-lg border border-gray-200 p-4">
                                    <p class="text-sm font-medium text-gray-600 mb-1">Total Incidents</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ $disciplineSummary['total'] }}</p>
                                </div>
                                <div class="bg-white rounded-lg border border-gray-200 p-4">
                                    <p class="text-sm font-medium text-gray-600 mb-1">Low</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ $disciplineSummary['low'] }}</p>
                                </div>
                                <div class="bg-white rounded-lg border border-gray-200 p-4">
                                    <p class="text-sm font-medium text-gray-600 mb-1">Medium</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ $disciplineSummary['medium'] }}</p>
                                </div>
                                <div class="bg-white rounded-lg border border-gray-200 p-4">
                                    <p class="text-sm font-medium text-gray-600 mb-1">High</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ $disciplineSummary['high'] }}</p>
                                </div>
                                <div class="bg-white rounded-lg border border-gray-200 p-4">
                                    <p class="text-sm font-medium text-gray-600 mb-1">Latest Incident</p>
                                    <p class="text-lg font-bold text-gray-900">
                                        {{ $disciplineSummary['latest_date']?->format('d M, Y') ?? 'None' }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2 border-t border-gray-200 pt-4">
                                {{-- The full history, on its own page. This
                                     panel deliberately holds counts only:
                                     the profile must not load a student's
                                     every past incident to show a
                                     summary. --}}
                                <a href="{{ route('students.discipline', $student->id) }}"
                                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    View Discipline History
                                </a>

                                <a href="{{ route('discipline.create', ['student_id' => $student->id]) }}"
                                   class="inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                                    Add Discipline Record
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Important Instructions & Guardian Agreement -->
                    <div class="space-y-4 lg:col-span-2">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                            Important Instructions &amp; Guardian Agreement
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Guardian Agreement</label>
                                <p class="mt-1">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                        {{ $student->instructions_accepted ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $student->instructions_accepted ? 'Accepted' : 'Not Accepted' }}
                                    </span>
                                </p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Accepted On</label>
                                <p class="text-gray-900">
                                    {{ $student->instructions_accepted_at?->format('d M, Y h:i A') ?? 'N/A' }}
                                </p>
                            </div>
                        </div>

                        {{-- Read only: displayed for reference, never editable here. --}}
                        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                            <x-admission-instructions :student="$student" />
                        </div>
                    </div>

                    <!-- Medical Information -->
                    @if($student->medical_information)
                        <div class="space-y-4 lg:col-span-2">
                            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Medical Information</h3>
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                <p class="text-gray-900">{{ $student->medical_information }}</p>
                            </div>
                        </div>
                    @endif

                    <!-- Notes -->
                    @if($student->notes)
                        <div class="space-y-4 lg:col-span-2">
                            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Notes</h3>
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <p class="text-gray-900">{{ $student->notes }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <div class="flex flex-col sm:flex-row justify-between items-center text-sm text-gray-600 space-y-2 sm:space-y-0">
                    <p>Created: {{ $student->created_at->format('d M, Y h:i A') }}</p>
                    <p>Last Updated: {{ $student->updated_at->format('d M, Y h:i A') }}</p>
                </div>
            </div>
        </div>
    </div>
</x-layout.admin>
