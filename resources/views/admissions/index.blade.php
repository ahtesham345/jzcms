@php
    // Whether anything is being filtered on right now. Derived from the
    // filters the controller actually read rather than from a list of query
    // string keys repeated in this file, so a filter added to
    // AdmissionApplicationFilters reaches the "Clear Filters" link and the
    // empty state without either being edited.
    $activeFilters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
@endphp

<x-layout.admin title="Admission Management">
    <x-slot name="header">
        Admission Management
    </x-slot>

    <div class="bg-white rounded-lg shadow-sm">
        <!-- Search and Filter Section -->
        <div class="px-6 py-4 border-b border-gray-200">
            {{-- The class dropdown is narrowed by the chosen department, the
                 same pattern the results, academic and attendance filters
                 use. --}}
            <form
                method="GET"
                action="{{ route('admissions.index') }}"
                class="space-y-4"
                x-data="{
                    departmentId: '{{ $filters['department_id'] }}',
                    academicClassId: '{{ $filters['academic_class_id'] }}',
                    classesByDepartment: {{ Js::from($classesByDepartment) }},
                    get classes() {
                        return this.departmentId ? (this.classesByDepartment[this.departmentId] ?? []) : []
                    },
                    onDepartmentChange() {
                        this.academicClassId = ''
                    },
                }"
                x-init="$nextTick(() => academicClassId = '{{ $filters['academic_class_id'] }}')"
            >
                <!-- Search Input -->
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input 
                        type="text" 
                        name="search" 
                        id="search"
                        value="{{ request('search') }}"
                        placeholder="Search by application no, student name, father name, or mobile..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>

                <!-- Filters Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Status Filter -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select 
                            name="status" 
                            id="status"
                            class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All Status</option>
                            @foreach(\App\Models\AdmissionApplication::STATUSES as $statusOption)
                                <option value="{{ $statusOption }}" {{ request('status') === $statusOption ? 'selected' : '' }}>{{ $statusOption }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Student Type Filter -->
                    <div>
                        <label for="student_type" class="block text-sm font-medium text-gray-700 mb-1">Student Type</label>
                        <select 
                            name="student_type" 
                            id="student_type"
                            class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All Types</option>
                            @foreach(\App\Models\AdmissionApplication::STUDENT_TYPES as $typeOption)
                                <option value="{{ $typeOption }}" {{ request('student_type') === $typeOption ? 'selected' : '' }}>{{ $typeOption }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Test Result Filter -->
                    <div>
                        <label for="test_result" class="block text-sm font-medium text-gray-700 mb-1">Test Result</label>
                        <select
                            name="test_result"
                            id="test_result"
                            class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All Results</option>
                            @foreach(\App\Models\AdmissionApplication::TEST_RESULTS as $resultOption)
                                <option value="{{ $resultOption }}" {{ request('test_result') === $resultOption ? 'selected' : '' }}>{{ $resultOption }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Gender Filter -->
                    <div>
                        <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                        <select 
                            name="gender" 
                            id="gender"
                            class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All Genders</option>
                            <option value="Male" {{ request('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                            <option value="Female" {{ request('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                        </select>
                    </div>

                    <!-- Academic Session Filter -->
                    <div>
                        <label for="academic_session_id" class="block text-sm font-medium text-gray-700 mb-1">Academic Session</label>
                        <select
                            name="academic_session_id"
                            id="academic_session_id"
                            class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            {{-- Opens on every session rather than the current
                                 one: applications filed before the session was
                                 recorded carry none, and a default would hide
                                 all of them from this page. --}}
                            <option value="">All Sessions</option>
                            @foreach($academicSessions as $session)
                                <option value="{{ $session->id }}" {{ $filters['academic_session_id'] === $session->id ? 'selected' : '' }}>
                                    {{ $session->name }}{{ $session->is_current ? ' (Current)' : '' }}
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
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Class Filter, narrowed by department -->
                    <div>
                        <label for="academic_class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                        <select
                            name="academic_class_id"
                            id="academic_class_id"
                            x-model="academicClassId"
                            :disabled="! departmentId"
                            class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100"
                        >
                            <option value="">All Classes</option>
                            <template x-for="option in classes" :key="option.id">
                                <option :value="option.id" x-text="option.name"></option>
                            </template>
                        </select>
                        <p class="mt-1 text-sm text-gray-500" x-show="! departmentId" x-cloak>Choose a department first.</p>
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
                    @if($activeFilters)
                        <a 
                            href="{{ route('admissions.index') }}"
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
                <h3 class="text-lg font-semibold text-gray-800">All Applications</h3>
                @if($applications->total() > 0)
                    <p class="text-sm text-gray-600 mt-1">
                        Showing {{ $applications->firstItem() }} to {{ $applications->lastItem() }} of {{ $applications->total() }} applications
                    </p>
                @endif
            </div>
            <div class="flex items-center space-x-2">
                <a
                    href="{{ route('admissions.test-scheduling') }}"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Test Scheduling
                </a>
                {{-- The notice board sheet of who passed the admission test,
                     carrying exactly the filters this page is showing, in
                     the chosen language, opened in a new tab. --}}
                <span class="inline-flex items-center rounded-lg overflow-hidden border border-gray-300">
                    <span class="inline-flex items-center px-3 py-2 text-gray-700 text-sm font-medium">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Print Passed Students
                    </span>
                    @foreach($reportLanguages as $code => $name)
                        <a
                            href="{{ route('admissions.passed-students.pdf', $pdfFilters + ['language' => $code]) }}"
                            target="_blank" rel="noopener"
                            class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 border-l border-gray-300 transition-colors"
                        >
                            {{ $name }}
                        </a>
                    @endforeach
                </span>
                <a
                    href="{{ route('admissions.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Application
                </a>
            </div>
        </div>

        @if($applications->count() > 0)
            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                #
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Application No
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Student Name
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Father Name
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Mobile
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Student Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Session
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Test Schedule
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Submitted Date
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($applications as $application)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $applications->firstItem() + $loop->index }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $application->application_number }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $application->student_name }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $application->gender }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $application->father_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $application->father_mobile }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                                        {{ $application->student_type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    {{-- Applications filed before the session
                                         was recorded say so plainly. They stay
                                         listed here; they are only left off the
                                         session-specific notices. --}}
                                    @if($application->academicSession)
                                        <span class="text-gray-900">{{ $application->academicSession->name }}</span>
                                    @else
                                        <span class="text-gray-400">Not Assigned</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $application->statusBadgeClasses() }}">
                                        {{ $application->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($application->hasTestSchedule())
                                        <div class="text-sm text-gray-900">
                                            {{ $application->test_date ? $application->test_date->format('d M, Y') : '—' }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ $application->formattedTestTime() ?? 'Time not set' }}
                                        </div>
                                        @if($application->test_result)
                                            <span class="inline-flex mt-1 px-2 py-0.5 text-xs font-semibold rounded-full {{ $application->testResultBadgeClasses() }}">
                                                {{ $application->test_result }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-sm text-gray-400">Not scheduled</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $application->created_at->format('d M, Y') }}</div>
                                    <div class="text-sm text-gray-500">{{ $application->created_at->format('h:i A') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <a href="{{ route('admissions.show', $application->id) }}" class="text-blue-600 hover:text-blue-800" title="View Application">
                                        <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                    <a href="{{ route('admissions.edit', $application->id) }}" class="text-yellow-600 hover:text-yellow-800" title="Edit Application">
                                        <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <form action="{{ route('admissions.destroy', $application->id) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this application?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800" title="Delete Application">
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
                {{ $applications->links() }}
            </div>
        @else
            <!-- Empty State -->
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    @if($activeFilters)
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    @else
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    @endif
                </svg>
                @if($activeFilters)
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No applications found matching your criteria</h3>
                    <p class="mt-1 text-sm text-gray-500">Try adjusting your search or filter criteria.</p>
                    <div class="mt-6">
                        <a 
                            href="{{ route('admissions.index') }}"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Clear all filters
                        </a>
                    </div>
                @else
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No applications found</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by adding a new admission application.</p>
                    <div class="mt-6">
                        <a 
                            href="{{ route('admissions.create') }}"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700"
                        >
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Application
                        </a>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-layout.admin>
