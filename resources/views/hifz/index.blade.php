@php
    // Built once: every "open a record" link carries the filters back so
    // saving returns to the roster the student was picked from.
    $carriedFilters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
    $hasFilters = $carriedFilters !== [];
@endphp

<x-layout.admin title="Hifz & Quran">
    <x-slot name="header">
        Hifz &amp; Quran
    </x-slot>

    <div class="space-y-6">
        <!-- Intro -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Hifz &amp; Quran — Daily Academic Record</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        What a madrassa student did on a day: Sabaq, Sabqi and Manzil for Hifz students, the
                        kitab and lesson for Dars-e-Nizami students. This is separate from Attendance, which
                        records only whether the student was present.
                    </p>
                    <p class="text-sm text-gray-600 mt-1">
                        Choose a date and a class below to open the roster, then add or correct each student's record.
                    </p>
                </div>

                <a href="{{ route('hifz.reports') }}"
                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors flex-shrink-0">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Reports &amp; Progress
                </a>
            </div>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Daily Records</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $summary['total'] }}</p>
                        <p class="text-sm text-gray-600 mt-2">Recorded days</p>
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
                        <p class="text-sm font-medium text-gray-600 mb-1">Hifz Records</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $summary['hifz'] }}</p>
                        <p class="text-sm text-gray-600 mt-2">Sabaq, Sabqi, Manzil</p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Dars-e-Nizami Records</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $summary['dars_e_nizami'] }}</p>
                        <p class="text-sm text-gray-600 mt-2">Kitab and lesson</p>
                    </div>
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Madrassa Students</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $summary['madrassa_students'] }}</p>
                        <p class="text-sm text-gray-600 mt-2">Active enrollments</p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        @if($filteredStudent)
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-center justify-between">
                <p class="text-sm text-blue-800">
                    Showing the daily records of
                    <a href="{{ route('students.show', $filteredStudent->id) }}" class="font-semibold underline">{{ $filteredStudent->full_name }}</a>
                    ({{ $filteredStudent->registration_number }}).
                </p>
                <a href="{{ route('hifz.index') }}" class="text-sm font-medium text-blue-700 hover:text-blue-900">Show all students</a>
            </div>
        @endif

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                {{-- Department -> Class -> Section narrow each other, the same
                     pattern the academic and attendance pages use. --}}
                <form
                    method="GET"
                    action="{{ route('hifz.index') }}"
                    class="space-y-4"
                    x-data="{
                        departmentId: '{{ $filters['department_id'] }}',
                        academicClassId: '{{ $filters['academic_class_id'] }}',
                        sectionId: '{{ $filters['section_id'] }}',
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
                    @if($filters['student_id'])
                        {{-- Kept across a search so narrowing one student's
                             history stays narrowed to that student. --}}
                        <input type="hidden" name="student_id" value="{{ $filters['student_id'] }}">
                    @endif

                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input
                            type="text"
                            name="search"
                            id="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Search by student name, registration number, or roll number..."
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                    </div>

                    {{-- Laid out in the order the workflow runs: date, then
                         session, then the department/class/section chain. --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Date -->
                        <div>
                            <label for="record_date" class="block text-sm font-medium text-gray-700 mb-1">
                                Date <span class="text-gray-400 font-normal">(for the roster)</span>
                            </label>
                            <input type="date" name="record_date" id="record_date" value="{{ $filters['record_date'] }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <p class="mt-1 text-sm text-gray-500">With a class, this loads the day's roster.</p>
                        </div>

                        <!-- Academic Session -->
                        <div>
                            <label for="academic_session_id" class="block text-sm font-medium text-gray-700 mb-1">Academic Session</label>
                            <select name="academic_session_id" id="academic_session_id"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Sessions</option>
                                @foreach($academicSessions as $session)
                                    <option value="{{ $session->id }}" {{ (int) $filters['academic_session_id'] === $session->id ? 'selected' : '' }}>
                                        {{ $session->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Programme -->
                        <div>
                            <label for="record_type" class="block text-sm font-medium text-gray-700 mb-1">Programme</label>
                            <select name="record_type" id="record_type"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Programmes</option>
                                @foreach($recordTypes as $type)
                                    <option value="{{ $type }}" {{ $filters['record_type'] === $type ? 'selected' : '' }}>{{ $type }}</option>
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

                    <div class="flex items-center space-x-2">
                        {{-- One submit for the whole page: it loads the day's
                             roster and narrows the records listing below with
                             the same conditions. --}}
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                            Load Daily Records
                        </button>
                        @if($hasFilters)
                            <a href="{{ route('hifz.index') }}"
                               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                Clear Filters
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- The day's roster -->
        @if($rosterState !== 'idle')
            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">
                        Daily Roster — {{ \Illuminate\Support\Carbon::parse($rosterHeading['date'])->format('l, d F Y') }}
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ $rosterHeading['academicClass']?->name ?? 'No class chosen' }}
                        @if($rosterHeading['section']) &middot; {{ $rosterHeading['section']->name }} @endif
                        @if($rosterHeading['department']) &middot; {{ $rosterHeading['department']->name }} @endif
                        @if($rosterHeading['session']) &middot; {{ $rosterHeading['session']->name }} @endif
                    </p>
                </div>

                @if($rosterState === 'off_day')
                    {{-- Sunday is off across the institution, so
                         there is no day's work to record. The save refuses the
                         date too; this is where the admin finds out first, and
                         nothing is queried or written in the meantime. --}}
                    <div class="px-6 py-10 text-center">
                        <div class="mx-auto w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h4 class="mt-3 text-base font-semibold text-gray-900">Weekly Off Day</h4>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ $rosterHeading['offDay'] }} is an off day. No daily record can be created for this date.
                        </p>
                        <p class="mt-1 text-sm text-gray-500">Choose a working day, Monday to Friday.</p>
                    </div>
                @elseif($rosterState === 'needs_class')
                    <div class="px-6 py-10 text-center">
                        <p class="text-sm font-medium text-gray-900">Choose a class to load the roster.</p>
                        <p class="mt-1 text-sm text-gray-500">
                            Pick a department first, then the class, and then Load Daily Records.
                        </p>
                    </div>
                @elseif($roster->isEmpty())
                    <div class="px-6 py-10 text-center">
                        <p class="text-sm font-medium text-gray-900">No madrassa students match these filters.</p>
                        <p class="mt-1 text-sm text-gray-500">
                            Only students with an active Madrassa enrollment appear here. School-only students are not part of this module.
                        </p>
                    </div>
                @else
                    @php
                        // Counted from what is already loaded, not requeried.
                        $recorded = $roster->filter(fn ($item) => $item->dailyRecordForDate !== null)->count();
                    @endphp

                    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200 text-sm text-gray-700">
                        {{ $roster->count() }} {{ $roster->count() === 1 ? 'student' : 'students' }} &middot;
                        <span class="font-medium text-emerald-700">{{ $recorded }} recorded</span> &middot;
                        <span class="font-medium text-gray-600">{{ $roster->count() - $recorded }} not recorded</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registration No.</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No.</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Program</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Today's Record</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                {{-- One row per student: a Hifz + School student
                                     appears once, through their madrassa
                                     enrollment. --}}
                                @foreach($roster as $enrollment)
                                    @php
                                        $record = $enrollment->dailyRecordForDate;
                                        $student = $enrollment->student;
                                        // Hifz or Dars-e-Nizami, worked out from
                                        // the student already in hand.
                                        $program = \App\Models\MadrassaDailyRecord::recordTypeForStudentType($student->student_type);
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <a href="{{ route('students.show', $enrollment->student_id) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                                {{ $student->full_name }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student->registration_number }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student->roll_number ?: '—' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->academicClass?->name ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment->section?->name ?? 'No section' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $program }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-700 max-w-md">
                                            {{-- Green for recorded, neutral grey
                                                 for not. Never red: an unrecorded
                                                 day is office work outstanding,
                                                 not an absence or a failure.
                                                 Attendance answers that question
                                                 in its own module. --}}
                                            @if($record)
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                    Recorded
                                                </span>
                                                <span class="block mt-1 text-sm text-gray-600">{{ $record->workSummary() }}</span>
                                            @else
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">
                                                    Not Recorded
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $record?->teacher?->full_name ?? '—' }}
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                            {{-- A day that already has a record
                                                 is only ever viewed or corrected.
                                                 Add is not offered, so a second
                                                 record is never attempted. --}}
                                            @if($record)
                                                <div class="flex items-center gap-2">
                                                    <a href="{{ route('hifz.show', $record->id) }}"
                                                       class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                                        View
                                                    </a>
                                                    <a href="{{ route('hifz.edit', ['record' => $record->id, 'filters' => $carriedFilters]) }}"
                                                       class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                                        Edit
                                                    </a>
                                                </div>
                                            @else
                                                <a href="{{ route('hifz.create', [
                                                        'student_academic_enrollment_id' => $enrollment->id,
                                                        'record_date' => $rosterHeading['date'],
                                                        'filters' => $carriedFilters,
                                                   ]) }}"
                                                   class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                                    Add Daily Record
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        <!-- Records already on file -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Daily Records</h3>
                @if($records->total() > 0)
                    <p class="text-sm text-gray-600 mt-1">
                        Showing {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }} daily records
                    </p>
                @endif
            </div>

            @if($records->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registration No.</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Programme</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Record Type</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Work</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            {{-- Class, section and programme all come from the
                                 stored enrollment, so a record made before a
                                 promotion keeps showing the class it was made
                                 in. --}}
                            @foreach($records as $record)
                                @php($enrollment = $record->studentAcademicEnrollment)
                                @php($student = $enrollment?->student)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $record->record_date->format('d M, Y') }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @if($student)
                                            <a href="{{ route('students.show', $student->id) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                                {{ $student->full_name }}
                                            </a>
                                        @else
                                            <span class="text-sm text-gray-500">N/A</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student?->registration_number ?? 'N/A' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment?->academicClass?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment?->section?->name ?? 'No section' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student?->student_type ?? 'N/A' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $record->recordTypeBadgeClasses() }}">
                                            {{ $record->record_type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-700 max-w-md">
                                        {{-- One line per part of the day, read
                                             straight from what was entered. No
                                             totals, no page or para arithmetic. --}}
                                        @forelse($record->workHighlights() as $label => $value)
                                            <div><span class="font-medium text-gray-900">{{ $label }}:</span> {{ $value }}</div>
                                        @empty
                                            <span class="text-gray-400">No work recorded</span>
                                        @endforelse
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $record->teacher?->full_name ?? 'Not recorded' }}</td>
                                    <td class="px-4 py-4 text-sm text-gray-700 max-w-xs">{{ $record->remarks ?: '—' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <a href="{{ route('hifz.show', $record->id) }}" class="text-blue-600 hover:text-blue-800">View</a>
                                        <a href="{{ route('hifz.edit', ['record' => $record->id, 'filters' => $carriedFilters]) }}" class="text-gray-700 hover:text-gray-900">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $records->links() }}
                </div>
            @else
                <div class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No daily records found.</h3>
                    @if($hasFilters)
                        <p class="mt-1 text-sm text-gray-500">Try adjusting your search or filter criteria.</p>
                        <div class="mt-6">
                            <a href="{{ route('hifz.index') }}"
                               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Clear all filters
                            </a>
                        </div>
                    @else
                        <p class="mt-1 text-sm text-gray-500">
                            Choose a date and a class above to open the day's roster and record what each student did.
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layout.admin>
