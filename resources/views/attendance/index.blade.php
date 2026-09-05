@php
    use App\Models\StudentAttendance;

    $teachingDates = collect($days)->reject(fn ($day) => $day['is_off_day'])->pluck('date')->values()->all();

    // The filters that define this sheet, carried through the period tabs
    // and the save so neither loses the month being worked on.
    $sheetParameters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
@endphp

<x-layout.admin title="Attendance">
    <x-slot name="header">
        Attendance
    </x-slot>

    <div class="space-y-6">
        <!-- Intro -->
        <div class="bg-white rounded-lg shadow-sm p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Monthly Attendance Entry</h2>
                <p class="text-sm text-gray-600 mt-1">
                    Choose an academic group and a month, then transcribe the paper attendance register for that month.
                    Madrassa is entered one period at a time; School is entered in the Morning only.
                    Sunday is the weekly off day. Saturday is a working day for both tracks.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('attendance.reports') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Attendance Reports
                </a>

                <a href="{{ route('attendance.session-summary') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Session Summary
                </a>
            </div>
        </div>

        <!-- Sheet selection -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Select Attendance Sheet</h3>
            </div>

            <div class="px-6 py-4">
                {{-- Department -> Class -> Section narrow each other, the same
                     pattern the enrollment and academic pages use. The track
                     reloads the form instead, because the period tabs it
                     decides are built on the server. --}}
                <form
                    method="GET"
                    action="{{ route('attendance.index') }}"
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
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Academic Session -->
                        <div>
                            <label for="academic_session_id" class="block text-sm font-medium text-gray-700 mb-1">Academic Session</label>
                            <select name="academic_session_id" id="academic_session_id"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select session</option>
                                @foreach($academicSessions as $session)
                                    <option value="{{ $session->id }}" {{ (int) $filters['academic_session_id'] === $session->id ? 'selected' : '' }}>
                                        {{ $session->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Academic Track -->
                        <div>
                            <label for="academic_track" class="block text-sm font-medium text-gray-700 mb-1">Academic Track</label>
                            {{-- Reloads the form: the track decides which
                                 periods exist, and that list is the server's
                                 to give rather than the browser's to guess. --}}
                            <select name="academic_track" id="academic_track"
                                @change="$el.form.submit()"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select track</option>
                                @foreach($academicTracks as $track)
                                    <option value="{{ $track }}" {{ $filters['academic_track'] === $track ? 'selected' : '' }}>{{ $track }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Department -->
                        <div>
                            <label for="department_id" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                            <select name="department_id" id="department_id"
                                x-model="departmentId" @change="onDepartmentChange()"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select department</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Class -->
                        <div>
                            <label for="academic_class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                            <select name="academic_class_id" id="academic_class_id"
                                x-model="academicClassId" @change="onClassChange()"
                                :disabled="! departmentId"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100">
                                <option value="">Select class</option>
                                <template x-for="option in classes" :key="option.id">
                                    <option :value="option.id" x-text="option.name"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-sm text-gray-500" x-show="! departmentId" x-cloak>Choose a department first.</p>
                        </div>

                        <!-- Section -->
                        <div>
                            <label for="section_id" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                            <select name="section_id" id="section_id"
                                x-model="sectionId"
                                :disabled="! academicClassId"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100">
                                {{-- A class may be run without sections, so the
                                     whole class is a valid sheet. --}}
                                <option value="">All sections</option>
                                <template x-for="option in sections" :key="option.id">
                                    <option :value="option.id" x-text="option.name"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-sm text-gray-500" x-show="academicClassId && sections.length === 0" x-cloak>
                                This class has no sections. The whole class will be entered together.
                            </p>
                        </div>

                        <!-- Month and Year -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                                <select name="month" id="month"
                                    class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    @foreach($months as $number => $name)
                                        <option value="{{ $number }}" {{ $filters['month'] === $number ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                                <select name="year" id="year"
                                    class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    @foreach($years as $year)
                                        <option value="{{ $year }}" {{ $filters['year'] === $year ? 'selected' : '' }}>{{ $year }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Load Attendance Sheet
                        </button>
                        <a href="{{ route('attendance.index') }}"
                           class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @if($sheet === null)
            <!-- Nothing selected yet -->
            <div class="bg-white rounded-lg shadow-sm px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No attendance sheet loaded.</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Select an academic session, track, department and class, choose the month, then load the sheet.
                </p>
            </div>
        @elseif($sheet->isEmpty())
            <!-- No students -->
            <div class="bg-white rounded-lg shadow-sm px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No students in this group.</h3>
                <p class="mt-1 text-sm text-gray-500">
                    No active enrollments match this academic session, track, department, class and section.
                </p>
            </div>
        @else
            <!-- Monthly attendance sheet -->
            <form method="POST" action="{{ route('attendance.store') }}"
                @submit="$refs.payload.value = JSON.stringify(changedCells)"
                x-data="{
                    cells: {{ Js::from($cells) }},
                    initial: {{ Js::from($initialCells) }},
                    students: {{ Js::from($studentNames) }},
                    dayLabels: {{ Js::from($dayLabels) }},
                    teachingDates: {{ Js::from($teachingDates) }},
                    enrollmentIds: {{ Js::from($sheet->pluck('id')->values()) }},
                    reason: { open: false, key: null, student: '', day: '', value: '', revertTo: '' },

                    cell(key) {
                        return this.cells[key] ?? { status: '', reason: '' }
                    },

                    /* Unmarked -> Present -> Absent -> Present. A cell never
                       returns to unmarked: clearing a mark that is already on
                       file is not something a paper register can express. */
                    toggle(key) {
                        const current = this.cell(key).status

                        if (current !== 'Present') {
                            this.cells[key].status = 'Present'
                            return
                        }

                        // Present -> Absent, which has to say why. Backing
                        // out of the dialog returns the cell to Present.
                        this.cells[key].status = 'Absent'
                        this.openReason(key, 'Present')
                    },

                    openReason(key, revertTo) {
                        const [enrollmentId, date] = key.split('|')

                        this.reason = {
                            open: true,
                            key: key,
                            student: this.students[enrollmentId] ?? '',
                            day: this.dayLabels[date] ?? date,
                            value: this.cell(key).reason ?? '',
                            revertTo: revertTo,
                        }

                        this.$nextTick(() => this.$refs.reasonInput?.focus())
                    },

                    saveReason() {
                        const value = this.reason.value.trim()
                        if (value === '') return

                        this.cells[this.reason.key].reason = value
                        this.reason.open = false
                    },

                    /* Backing out of the dialog puts the cell back where it
                       was, so an absence is never left without its reason. */
                    cancelReason() {
                        this.cells[this.reason.key].status = this.reason.revertTo
                        this.reason.open = false
                    },

                    boxClasses(key) {
                        const status = this.cell(key).status
                        if (status === 'Present') return 'bg-green-500 border-green-600 hover:bg-green-600'
                        if (status === 'Absent') return 'bg-red-500 border-red-600 hover:bg-red-600'
                        return 'bg-gray-200 border-gray-300 hover:bg-gray-300'
                    },

                    cellTitle(key) {
                        const [enrollmentId, date] = key.split('|')
                        const cell = this.cell(key)
                        const status = cell.status || 'Unmarked'
                        const reason = cell.status === 'Absent' && cell.reason ? ' - ' + cell.reason : ''

                        return (this.students[enrollmentId] ?? '') + ', ' + (this.dayLabels[date] ?? date) + ': ' + status + reason
                    },

                    /* Only what the administrator actually changed is sent.
                       Days not reached yet stay unmarked, and rows already on
                       file are left alone unless they were edited. */
                    signature(cell) {
                        if (! cell) return ''
                        return cell.status === 'Absent' ? 'Absent|' + (cell.reason ?? '') : (cell.status ?? '')
                    },

                    get changedCells() {
                        return Object.keys(this.cells)
                            .filter(key => this.cells[key].status !== ''
                                && this.signature(this.cells[key]) !== this.signature(this.initial[key]))
                            .map(key => {
                                const [enrollmentId, date] = key.split('|')

                                return {
                                    student_academic_enrollment_id: Number(enrollmentId),
                                    attendance_date: date,
                                    status: this.cells[key].status,
                                    absence_reason: this.cells[key].status === 'Absent' ? this.cells[key].reason : null,
                                }
                            })
                    },

                    get changedCount() { return this.changedCells.length },
                    get presentCount() { return Object.values(this.cells).filter(cell => cell.status === 'Present').length },
                    get absentCount() { return Object.values(this.cells).filter(cell => cell.status === 'Absent').length },
                    get unmarkedCount() { return Object.values(this.cells).filter(cell => cell.status === '').length },

                    /* Scoped to this period and these students, weekends
                       excluded, and it never touches a cell that already
                       carries a mark. */
                    markRemainingPresent() {
                        this.enrollmentIds.forEach(id => {
                            this.teachingDates.forEach(date => {
                                const key = id + '|' + date
                                if (this.cells[key] && this.cells[key].status === '') {
                                    this.cells[key].status = 'Present'
                                }
                            })
                        })
                    },

                    resetChanges() {
                        this.cells = JSON.parse(JSON.stringify(this.initial))
                        this.reason.open = false
                    },

                    confirmLeave(event) {
                        if (this.changedCount > 0 && ! window.confirm('You have unsaved attendance changes. Leave without saving?')) {
                            event.preventDefault()
                        }
                    },
                }">
                @csrf

                {{-- The group and month the sheet was drawn for. Re-checked
                     against every submitted row on the server; carried here
                     so the save knows which sheet it is saving, not so it
                     can be trusted. --}}
                @foreach($sheetParameters as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach

                {{-- The month arrives as one JSON field: a class of thirty
                     over a full month is several hundred cells, which would
                     run past PHP's max_input_vars as individual inputs. --}}
                <input type="hidden" name="sheet" x-ref="payload" value="">

                <div class="bg-white rounded-lg shadow-sm">
                    <!-- Sheet heading -->
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">
                            {{ collect([
                                $group['track'],
                                $group['department']?->name,
                                $group['academicClass']?->name,
                                $group['section'] ? 'Section ' . $group['section']->name : 'All sections',
                            ])->filter()->implode(' - ') }}
                        </h3>
                        <div class="mt-1 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                            <span class="font-medium text-gray-800">{{ $monthLabel }}</span>
                            <span>Session: {{ $group['session']?->name ?? '—' }}</span>
                            <span>Teaching days: {{ $summary['teaching_days'] }}</span>
                        </div>
                    </div>

                    <!-- Period -->
                    <div class="px-6 py-4 border-b border-gray-200">
                        @if(count($availablePeriods) > 1)
                            {{-- Each period is its own sheet and saves on its
                                 own, so switching tabs reloads that period's
                                 records. --}}
                            <nav class="flex flex-wrap gap-2" aria-label="Attendance period">
                                @foreach($availablePeriods as $period)
                                    <a href="{{ route('attendance.index', array_merge($sheetParameters, ['attendance_period' => $period])) }}"
                                       @click="confirmLeave($event)"
                                       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $filters['attendance_period'] === $period ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                                        {{ $period }}
                                    </a>
                                @endforeach
                            </nav>
                        @else
                            <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                {{ $filters['attendance_period'] }}
                            </span>
                            <p class="mt-1 text-xs text-gray-500">School attendance is recorded once a day, in the Morning.</p>
                        @endif
                    </div>

                    @if($errors->any())
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <ul class="list-disc list-inside text-sm text-red-800 space-y-1">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    <!-- Summary and marking controls -->
                    <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-4 text-sm">
                            <span class="text-gray-500">Students: <span class="font-semibold text-gray-900">{{ $summary['students'] }}</span></span>
                            <span class="inline-flex items-center">
                                <span class="w-3 h-3 rounded bg-green-500 mr-2"></span>
                                Present: <span class="font-semibold ml-1" x-text="presentCount"></span>
                            </span>
                            <span class="inline-flex items-center">
                                <span class="w-3 h-3 rounded bg-red-500 mr-2"></span>
                                Absent: <span class="font-semibold ml-1" x-text="absentCount"></span>
                            </span>
                            <span class="inline-flex items-center">
                                <span class="w-3 h-3 rounded bg-gray-300 mr-2"></span>
                                Unmarked: <span class="font-semibold ml-1" x-text="unmarkedCount"></span>
                            </span>
                            <span class="text-gray-500">Off days excluded</span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" @click="markRemainingPresent()"
                                title="Fills only the cells that are still unmarked. Existing attendance is left alone."
                                class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm">
                                Mark remaining Present
                            </button>
                            <button type="button" @click="resetChanges()"
                                :disabled="changedCount === 0"
                                class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                Reset unsaved changes
                            </button>

                            {{-- Prints what is saved, so unsaved marks are
                                 warned about rather than quietly left off the
                                 paper. --}}
                            <a href="{{ route('attendance.print', $sheetParameters) }}"
                               target="_blank"
                               @click="changedCount > 0 && ! window.confirm('The printed sheet shows saved attendance only. Print anyway?') && $event.preventDefault()"
                               class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm">
                                Print Attendance Sheet
                            </a>
                        </div>
                    </div>

                    <!-- The month -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full border-separate border-spacing-0">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col"
                                        class="sticky left-0 z-20 bg-gray-50 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-r border-gray-200 min-w-[15rem]">
                                        Student
                                    </th>
                                    @foreach($days as $day)
                                        <th scope="col"
                                            class="px-1 py-2 text-center text-xs font-medium border-b border-gray-200 {{ $day['is_off_day'] ? 'bg-amber-50 text-amber-700' : 'text-gray-500' }}">
                                            <div class="font-semibold">{{ $day['day'] }}</div>
                                            <div class="text-[10px] font-normal">{{ $day['is_off_day'] ? 'OFF' : $day['weekday'] }}</div>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                @foreach($sheet as $enrollment)
                                    <tr class="hover:bg-gray-50">
                                        <td class="sticky left-0 z-10 bg-white px-4 py-2 border-b border-r border-gray-200">
                                            <div class="flex items-baseline gap-2">
                                                <span class="text-xs text-gray-400">{{ $loop->iteration }}</span>
                                                <a href="{{ route('students.show', $enrollment->student_id) }}"
                                                   class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                                    {{ $enrollment->student->full_name }}
                                                </a>
                                            </div>
                                            <div class="text-xs text-gray-500 ml-6">
                                                {{ $enrollment->student->registration_number }}
                                                @if($enrollment->student->roll_number)
                                                    &middot; Roll {{ $enrollment->student->roll_number }}
                                                @endif
                                                @unless($group['section'])
                                                    &middot; {{ $enrollment->section?->name ?? 'No section' }}
                                                @endunless
                                            </div>
                                        </td>

                                        @foreach($days as $day)
                                            @if($day['is_off_day'])
                                                {{-- Not a cell at all: no button, nothing to
                                                     submit, and the backend refuses the date
                                                     even if one were forged. --}}
                                                <td class="bg-amber-50 border-b border-gray-200 px-1 py-2 text-center text-xs text-amber-600">
                                                    &ndash;
                                                </td>
                                            @else
                                                @php($key = StudentAttendance::cellKey($enrollment->id, $day['date']))
                                                <td class="border-b border-gray-200 px-1 py-2 text-center">
                                                    <button type="button"
                                                        @click="toggle('{{ $key }}')"
                                                        :class="boxClasses('{{ $key }}')"
                                                        :title="cellTitle('{{ $key }}')"
                                                        class="w-7 h-7 rounded border-2 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-blue-500">
                                                    </button>
                                                </td>
                                            @endif
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Save -->
                    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center gap-3">
                        <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Save Attendance
                        </button>
                        <p class="text-sm text-gray-600">
                            <span x-text="changedCount"></span> unsaved change(s).
                            A month can be entered over several sittings; unmarked days are left as they are.
                        </p>
                    </div>
                </div>

                <!-- Absence reason -->
                <div x-show="reason.open" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-900 bg-opacity-50" @click="cancelReason()"></div>

                    <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md p-6"
                         @keydown.escape.window="reason.open && cancelReason()">
                        <h3 class="text-lg font-semibold text-gray-800">Reason for absence</h3>
                        <p class="text-sm text-gray-600 mt-1">
                            <span class="font-medium" x-text="reason.student"></span>
                            &middot; <span x-text="reason.day"></span>
                            &middot; {{ $filters['attendance_period'] }}
                        </p>

                        <div class="mt-4">
                            <label for="absence-reason" class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                            <input type="text" id="absence-reason"
                                x-ref="reasonInput"
                                x-model="reason.value"
                                @keydown.enter.prevent="saveReason()"
                                list="absence-reasons"
                                placeholder="Sick, family issue, emergency..."
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <p class="mt-1 text-sm text-gray-500">An absence must say why. Cancelling leaves the day as it was.</p>
                        </div>

                        <div class="mt-6 flex justify-end gap-2">
                            <button type="button" @click="cancelReason()"
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="button" @click="saveReason()"
                                :disabled="reason.value.trim() === ''"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed">
                                Save Reason
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Suggestions only: the reason is free text so an administrator
                 can record what actually happened. --}}
            <datalist id="absence-reasons">
                @foreach($absenceReasons as $absenceReason)
                    <option value="{{ $absenceReason }}"></option>
                @endforeach
            </datalist>
        @endif
    </div>
</x-layout.admin>
