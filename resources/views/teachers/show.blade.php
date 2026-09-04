<x-layout.admin title="Teacher Profile">
    <x-slot name="header">
        Teacher Profile
    </x-slot>

    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <!-- Header -->
            <div class="px-6 py-6 bg-gradient-to-r from-blue-600 to-blue-700">
                <div class="flex flex-col sm:flex-row items-center sm:items-start space-y-4 sm:space-y-0 sm:space-x-6">
                    <!-- Photo -->
                    <div class="flex-shrink-0">
                        <div class="w-32 h-32 rounded-full bg-white/20 flex items-center justify-center border-4 border-white/30 overflow-hidden">
                            @if($teacher->hasPhoto())
                                <img src="{{ $teacher->photoUrl() }}" class="w-32 h-32 rounded-full object-cover" alt="{{ $teacher->full_name }}">
                            @else
                                <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </div>
                    </div>

                    <!-- Basic Info -->
                    <div class="text-center sm:text-left">
                        <h2 class="text-2xl font-bold text-white">{{ $teacher->full_name }}</h2>
                        <p class="text-blue-100 mt-1">Teacher ID: {{ $teacher->teacher_id }}</p>
                        <div class="mt-3">
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $teacher->statusBadgeClasses() }}">
                                {{ $teacher->teacher_status }}
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
                                <label class="text-sm font-medium text-gray-500">Teacher ID</label>
                                <p class="text-gray-900">{{ $teacher->teacher_id }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Full Name</label>
                                <p class="text-gray-900">{{ $teacher->full_name }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Father Name</label>
                                <p class="text-gray-900">{{ $teacher->father_name ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Date of Birth</label>
                                <p class="text-gray-900">{{ $teacher->date_of_birth?->format('d M, Y') ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Gender</label>
                                <p class="text-gray-900">{{ $teacher->gender }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">CNIC Number</label>
                                <p class="text-gray-900">{{ $teacher->cnic_number ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Contact Information</h3>

                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Mobile Number</label>
                                <p class="text-gray-900">{{ $teacher->mobile_number }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Alternate Mobile</label>
                                <p class="text-gray-900">{{ $teacher->alternate_mobile ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Email</label>
                                <p class="text-gray-900">{{ $teacher->email ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Address</label>
                                <p class="text-gray-900 whitespace-pre-line">{{ $teacher->address ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Professional Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Professional Information</h3>

                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Qualification</label>
                                <p class="text-gray-900">{{ $teacher->qualification ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Specialization</label>
                                <p class="text-gray-900">{{ $teacher->specialization ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Joining Date</label>
                                <p class="text-gray-900">{{ $teacher->joining_date?->format('d M, Y') ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Status</label>
                                <p class="mt-1">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $teacher->statusBadgeClasses() }}">
                                        {{ $teacher->teacher_status }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Classes Taught -->
                    <div class="space-y-4 lg:col-span-2">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Classes Taught</h3>

                        @if($teacher->academicClasses->isEmpty())
                            <p class="text-gray-900">No classes assigned.</p>
                        @else
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($teacher->academicClasses->groupBy(fn ($class) => $class->department?->name ?? 'Unassigned') as $departmentName => $classes)
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <h4 class="text-base font-semibold text-gray-800 mb-2">{{ $departmentName }} Department</h4>
                                        <ul class="space-y-1">
                                            @foreach($classes as $class)
                                                <li class="flex items-center text-gray-900">
                                                    <span class="mr-2 text-gray-400">&bull;</span>
                                                    {{ $class->name }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Additional Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Additional Information</h3>

                        <div>
                            <label class="text-sm font-medium text-gray-500">Notes</label>
                            @if($teacher->notes)
                                <div class="mt-1 bg-gray-50 border border-gray-200 rounded-lg p-4">
                                    <p class="text-gray-900 whitespace-pre-line">{{ $teacher->notes }}</p>
                                </div>
                            @else
                                <p class="text-gray-900">N/A</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <div class="flex flex-col sm:flex-row justify-between items-center text-sm text-gray-600 space-y-3 sm:space-y-0">
                    <div class="space-y-1 text-center sm:text-left">
                        <p>Created: {{ $teacher->created_at->format('d M, Y h:i A') }}</p>
                        <p>Last Updated: {{ $teacher->updated_at->format('d M, Y h:i A') }}</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <a href="{{ route('teachers.index') }}"
                           class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-white transition-colors">
                            Back to List
                        </a>
                        <a href="{{ route('teachers.edit', $teacher->id) }}"
                           class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Edit Teacher
                        </a>
                        <form action="{{ route('teachers.destroy', $teacher->id) }}" method="POST" class="inline"
                              onsubmit="return confirm('Are you sure you want to delete this teacher?');">
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
