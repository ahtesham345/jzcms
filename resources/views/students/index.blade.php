<x-layout.admin title="Student Management">
    <x-slot name="header">
        Student Management
    </x-slot>

    <div class="bg-white rounded-lg shadow-sm">
        <!-- Search and Filter Section -->
        <div class="px-6 py-4 border-b border-gray-200">
            {{--
                Department -> class -> section, narrowed in the browser from
                the same maps the Add Student form uses. Picking a department
                leaves only that department's classes on offer, and only the
                sections under them; picking a class narrows the sections
                again. Changing the department clears both, so a School
                filter can never be left carrying a Hifz class.
            --}}
            <form method="GET" action="{{ route('students.index') }}" class="space-y-4"
                x-data="{
                    departmentId: {{ Js::from((string) request('department_id')) }},
                    classId: {{ Js::from((string) request('academic_class_id')) }},
                    sectionId: {{ Js::from((string) request('section_id')) }},
                    classesByDepartment: {{ Js::from($classesByDepartment) }},
                    sectionsByClass: {{ Js::from($sectionsByClass) }},
                    byName(options) {
                        return [...options].sort((a, b) => a.name.localeCompare(b.name))
                    },
                    classesFor(departmentId) {
                        return departmentId
                            ? (this.classesByDepartment[departmentId] ?? [])
                            : Object.values(this.classesByDepartment).flat()
                    },
                    classOptions() {
                        return this.departmentId
                            ? this.classesFor(this.departmentId)
                            : this.byName(this.classesFor(''))
                    },
                    sectionOptions() {
                        if (this.classId) {
                            return this.sectionsByClass[this.classId] ?? []
                        }

                        return this.byName(
                            this.classesFor(this.departmentId)
                                .flatMap((option) => this.sectionsByClass[option.id] ?? [])
                        )
                    },
                    onDepartmentChange() {
                        this.classId = ''
                        this.sectionId = ''
                    },
                    onClassChange() {
                        this.sectionId = ''
                    },
                }"
            >
                <!-- Search Input -->
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input 
                        type="text" 
                        name="search" 
                        id="search"
                        value="{{ request('search') }}"
                        placeholder="Search by registration no, roll no, student name, or father name..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>

                <!-- Filters Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                    <!-- Academic Session Filter -->
                    <div>
                        <label for="academic_session_id" class="block text-sm font-medium text-gray-700 mb-1">Session</label>
                        <select 
                            name="academic_session_id" 
                            id="academic_session_id"
                            class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All Sessions</option>
                            @foreach($academicSessions as $session)
                                <option value="{{ $session->id }}" {{ request('academic_session_id') == $session->id ? 'selected' : '' }}>
                                    {{ $session->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Department Filter -->
                    <div>
                        <label for="department_id" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select 
                            name="department_id" 
                            id="department_id"
                            x-model="departmentId"
                            @change="onDepartmentChange()"
                            class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All Departments</option>
                            @foreach($departmentNamesById as $departmentId => $departmentName)
                                <option value="{{ $departmentId }}" {{ request('department_id') == $departmentId ? 'selected' : '' }}>
                                    {{ $departmentName }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Academic Class Filter, narrowed by department -->
                    <div>
                        <label for="academic_class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                        <select 
                            name="academic_class_id" 
                            id="academic_class_id"
                            x-model="classId"
                            @change="onClassChange()"
                            class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All Classes</option>
                            <template x-for="option in classOptions()" :key="option.id">
                                <option :value="option.id" x-text="option.name"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Section Filter, narrowed by class and then department -->
                    <div>
                        <label for="section_id" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                        <select 
                            name="section_id" 
                            id="section_id"
                            x-model="sectionId"
                            class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All Sections</option>
                            <template x-for="option in sectionOptions()" :key="option.id">
                                <option :value="option.id" x-text="option.name"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Student Status Filter -->
                    <div>
                        <label for="student_status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select 
                            name="student_status" 
                            id="student_status"
                            class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All Status</option>
                            <option value="Active" {{ request('student_status') == 'Active' ? 'selected' : '' }}>Active</option>
                            <option value="Passed" {{ request('student_status') == 'Passed' ? 'selected' : '' }}>Passed</option>
                            <option value="Left" {{ request('student_status') == 'Left' ? 'selected' : '' }}>Left</option>
                        </select>
                    </div>

                    <!-- Student Type Filter -->
                    <div>
                        <label for="student_type" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select 
                            name="student_type" 
                            id="student_type"
                            class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All Types</option>
                            <option value="Hifz" {{ request('student_type') == 'Hifz' ? 'selected' : '' }}>Hifz</option>
                            <option value="Hifz + School" {{ request('student_type') == 'Hifz + School' ? 'selected' : '' }}>Hifz + School</option>
                            <option value="School" {{ request('student_type') == 'School' ? 'selected' : '' }}>School</option>
                            <option value="Dars-e-Nizami + Computer" {{ request('student_type') == 'Dars-e-Nizami + Computer' ? 'selected' : '' }}>Dars-e-Nizami + Computer</option>
                            <option value="Dars-e-Nizami" {{ request('student_type') == 'Dars-e-Nizami' ? 'selected' : '' }}>Dars-e-Nizami</option>
                        </select>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center space-x-2">
                    <button 
                        type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Search
                    </button>
                    @if(request()->hasAny(['search', 'academic_session_id', 'department_id', 'academic_class_id', 'section_id', 'student_status', 'student_type']))
                        <a 
                            href="{{ route('students.index') }}"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Clear Filters
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Table Header -->
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">All Students</h3>
                @if($students->total() > 0)
                    <p class="text-sm text-gray-600 mt-1">
                        Showing {{ $students->firstItem() }} to {{ $students->lastItem() }} of {{ $students->total() }} students
                    </p>
                @endif
            </div>
            <a 
                href="{{ route('students.create') }}" 
                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Student
            </a>
        </div>

        @if($students->count() > 0)
            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                #
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Registration No
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Student Name
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Class / Semester
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Section
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($students as $student)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $students->firstItem() + $loop->index }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $student->registration_number }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center overflow-hidden">
                                                @if($student->photo && \Storage::disk('public')->exists($student->photo))
                                                    <img src="{{ asset('storage/' . $student->photo) }}" class="h-10 w-10 rounded-full object-cover" alt="{{ $student->full_name }}">
                                                @else
                                                    <span class="text-sm font-medium text-gray-700">
                                                        {{ substr($student->full_name, 0, 1) }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $student->full_name }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ $student->father_name }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $student->placementClassNames() ?: '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $student->section?->name ?? '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                        {{ $student->student_status == 'Active' ? 'bg-green-100 text-green-800' : 
                                           ($student->student_status == 'Passed' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800') }}">
                                        {{ $student->student_status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                                        {{ $student->student_type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <a href="{{ route('students.show', $student->id) }}" class="text-blue-600 hover:text-blue-800" title="View Student">
                                        <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                    <a href="{{ route('students.edit', $student->id) }}" class="text-yellow-600 hover:text-yellow-800" title="Edit Student">
                                        <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <form action="{{ route('students.destroy', $student->id) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this student?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800" title="Delete Student">
                                            <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $students->links() }}
            </div>
        @else
            <!-- Empty State -->
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    @if(request()->hasAny(['search', 'academic_session_id', 'department_id', 'academic_class_id', 'section_id', 'student_status', 'student_type']))
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    @else
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"/>
                    @endif
                </svg>
                @if(request()->hasAny(['search', 'academic_session_id', 'department_id', 'academic_class_id', 'section_id', 'student_status', 'student_type']))
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No students found matching your criteria</h3>
                    <p class="mt-1 text-sm text-gray-500">Try adjusting your search or filter criteria.</p>
                    <div class="mt-6">
                        <a 
                            href="{{ route('students.index') }}"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Clear all filters
                        </a>
                    </div>
                @else
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No students found</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by adding a new student.</p>
                    <div class="mt-6">
                        <a 
                            href="{{ route('students.create') }}"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700"
                        >
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Student
                        </a>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-layout.admin>
