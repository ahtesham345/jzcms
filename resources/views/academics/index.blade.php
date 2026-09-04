<x-layout.admin title="Academic Management">
    <x-slot name="header">
        Academic Management
    </x-slot>

    <div class="space-y-6">
        <!-- Intro -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-xl font-semibold text-gray-800">Academic Management</h2>
            <p class="text-sm text-gray-600 mt-1">
                Manage and review student academic records, enrollments and academic progression.
            </p>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Active Academic Records</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $summary['active_records'] }}</p>
                        <p class="text-sm text-gray-600 mt-2">Active enrollments</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Madrassa Students</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $summary['madrassa_students'] }}</p>
                        <p class="text-sm text-gray-600 mt-2">Distinct students</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">School Students</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $summary['school_students'] }}</p>
                        <p class="text-sm text-gray-600 mt-2">Distinct students</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Dual Track Students</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $summary['dual_track_students'] }}</p>
                        <p class="text-sm text-gray-600 mt-2">Madrassa and School</p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm">
            <!-- Search and Filters -->
            <div class="px-6 py-4 border-b border-gray-200">
                {{-- Department -> Class -> Section narrow each other, the same
                     pattern the enrollment and promotion forms use. --}}
                <form
                    method="GET"
                    action="{{ route('academics.index') }}"
                    class="space-y-4"
                    x-data="{
                        departmentId: '{{ request('department_id') }}',
                        academicClassId: '{{ request('academic_class_id') }}',
                        sectionId: '{{ request('section_id') }}',
                        classesByDepartment: {{ Js::from($classesByDepartment) }},
                        sectionsByClass: {{ Js::from($sectionsByClass) }},
                        get classes() {
                            return this.departmentId ? (this.classesByDepartment[this.departmentId] ?? []) : []
                        },
                        get sections() {
                            return this.academicClassId ? (this.sectionsByClass[this.academicClassId] ?? []) : []
                        },
                        onDepartmentChange() {
                            this.academicClassId = ''
                            this.sectionId = ''
                        },
                        onClassChange() { this.sectionId = '' },
                    }"
                >
                    <!-- Search -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input
                            type="text"
                            name="search"
                            id="search"
                            value="{{ request('search') }}"
                            placeholder="Search by student name, registration number, or roll number..."
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Academic Session -->
                        <div>
                            <label for="academic_session_id" class="block text-sm font-medium text-gray-700 mb-1">Academic Session</label>
                            <select name="academic_session_id" id="academic_session_id"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Sessions</option>
                                @foreach($academicSessions as $session)
                                    <option value="{{ $session->id }}" {{ (int) request('academic_session_id') === $session->id ? 'selected' : '' }}>
                                        {{ $session->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Academic Track -->
                        <div>
                            <label for="academic_track" class="block text-sm font-medium text-gray-700 mb-1">Academic Track</label>
                            <select name="academic_track" id="academic_track"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Tracks</option>
                                @foreach($academicTracks as $track)
                                    <option value="{{ $track }}" {{ request('academic_track') === $track ? 'selected' : '' }}>{{ $track }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Enrollment Status -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Enrollment Status</label>
                            <select name="status" id="status"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Statuses</option>
                                @foreach($enrollmentStatuses as $statusOption)
                                    <option value="{{ $statusOption }}" {{ request('status') === $statusOption ? 'selected' : '' }}>{{ $statusOption }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Department -->
                        <div>
                            <label for="department_id" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                            <select name="department_id" id="department_id"
                                x-model="departmentId" @change="onDepartmentChange()"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Departments</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Class, narrowed by department -->
                        <div>
                            <label for="academic_class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                            <select name="academic_class_id" id="academic_class_id"
                                x-model="academicClassId" @change="onClassChange()" :disabled="! departmentId"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100">
                                <option value="">All Classes</option>
                                <template x-for="option in classes" :key="option.id">
                                    <option :value="option.id" x-text="option.name"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-sm text-gray-500" x-show="! departmentId" x-cloak>Choose a department first.</p>
                        </div>

                        <!-- Section, narrowed by class -->
                        <div>
                            <label for="section_id" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                            <select name="section_id" id="section_id"
                                x-model="sectionId" :disabled="! academicClassId || sections.length === 0"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100">
                                <option value="">All Sections</option>
                                <template x-for="option in sections" :key="option.id">
                                    <option :value="option.id" x-text="option.name"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-sm text-gray-500" x-show="academicClassId && sections.length === 0" x-cloak>
                                This class has no sections.
                            </p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center space-x-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            Search
                        </button>
                        @if(request()->hasAny(['search', 'academic_session_id', 'department_id', 'academic_class_id', 'section_id', 'academic_track', 'status']))
                            <a href="{{ route('academics.index') }}"
                               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                Clear Filters
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Table Header -->
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Student Academic Records</h3>
                @if($records->total() > 0)
                    <p class="text-sm text-gray-600 mt-1">
                        Showing {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }} academic records
                    </p>
                @endif
            </div>

            @if($records->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registration No.</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Type</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Session</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Track</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            {{-- One row per enrollment: a dual-track student
                                 appears once per active track rather than
                                 having one of them hidden. --}}
                            @foreach($records as $record)
                                @php($student = $record->student)
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
                                                <div class="text-sm text-gray-500">{{ $student->registration_number }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student->registration_number }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student->student_type }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $record->academicSession?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $record->academic_track }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $record->department?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $record->academicClass?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $record->section?->name ?? 'No section' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $record->statusBadgeClasses() }}">
                                            {{ $record->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                        {{-- Every action goes to the existing
                                             workflow; nothing is reimplemented
                                             here. --}}
                                        <div x-data="{ open: false }" class="relative">
                                            <button type="button" @click="open = ! open" @click.away="open = false"
                                                    class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                                Actions
                                                <svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>

                                            <div x-show="open" x-cloak
                                                 class="absolute right-0 mt-2 w-52 bg-white rounded-md shadow-lg py-1 ring-1 ring-black ring-opacity-5 z-40">
                                                <a href="{{ route('academics.enrollments.show', $record->id) }}"
                                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    View Academic Record
                                                </a>
                                                <a href="{{ route('academics.groups', array_filter([
                                                        'session' => $record->academic_session_id,
                                                        'track' => $record->academic_track,
                                                        'department' => $record->department_id,
                                                        'class' => $record->academic_class_id,
                                                        'section' => $record->section_id,
                                                   ], fn ($value) => $value !== null)) }}"
                                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    View Group
                                                </a>
                                                <a href="{{ route('students.show', $student->id) }}"
                                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    View Student
                                                </a>
                                                <a href="{{ route('students.show', $student->id) }}#academic-history"
                                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    Academic History
                                                </a>
                                                <a href="{{ route('students.show', $student->id) }}#academic-history"
                                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    Add Enrollment
                                                </a>
                                                @if($student->canBePromoted())
                                                    <a href="{{ route('students.promote', $student->id) }}"
                                                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        Promote
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $records->links() }}
                </div>
            @else
                <!-- Empty State -->
                <div class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        @if(request()->hasAny(['search', 'academic_session_id', 'department_id', 'academic_class_id', 'section_id', 'academic_track', 'status']))
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        @else
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        @endif
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No academic records found.</h3>
                    @if(request()->hasAny(['search', 'academic_session_id', 'department_id', 'academic_class_id', 'section_id', 'academic_track', 'status']))
                        <p class="mt-1 text-sm text-gray-500">Try adjusting your search or filter criteria.</p>
                        <div class="mt-6">
                            <a href="{{ route('academics.index') }}"
                               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Clear all filters
                            </a>
                        </div>
                    @else
                        <p class="mt-1 text-sm text-gray-500">
                            Academic records appear here once students are enrolled. Enrollments are added from a student's profile.
                        </p>
                        <div class="mt-6">
                            <a href="{{ route('students.index') }}"
                               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                                Go to Student Management
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layout.admin>
