@php
    use App\Models\StudentPrayerAttendance;

    // The filters that define this sheet, carried through the save so it
    // does not lose the month being worked on.
    $sheetParameters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
@endphp

<x-layout.admin title="Prayer Attendance">
    <x-slot name="header">
        Prayer Attendance
    </x-slot>

    <div class="space-y-6">
        <!-- Intro -->
        <div class="bg-white rounded-lg shadow-sm p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Monthly Prayer Attendance Entry</h2>
                <p class="text-sm text-gray-600 mt-1">
                    Fajr, Zuhr, Asr, Maghrib and Isha for Madrassa students. Choose a class and a month, then
                    transcribe the paper prayer register for that month. Saturday and Sunday are off days.
                </p>
                {{-- Said on the page, not only in the code: two registers,
                     kept separately, neither one derived from the other. --}}
                <p class="text-sm text-gray-500 mt-2">
                    Separate from Attendance, which records whether the student was in class. School students do
                    not appear here.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 flex-shrink-0">
                <a href="{{ route('prayer-attendance.reports', array_filter([
                        'academic_session_id' => $filters['academic_session_id'],
                        'department_id' => $filters['department_id'],
                        'academic_class_id' => $filters['academic_class_id'],
                        'section_id' => $filters['section_id'],
                        'month' => $filters['month'],
                        'year' => $filters['year'],
                   ])) }}"
                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors whitespace-nowrap">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Prayer Reports
                </a>

                <a href="{{ route('attendance.index') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    Academic Attendance
                </a>
            </div>
        </div>

        <!-- Sheet selection -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Select Prayer Sheet</h3>
                {{-- No track selector: the prayer register is the madrassa's,
                     so the track is fixed rather than chosen. --}}
                <p class="text-sm text-gray-600 mt-1">Madrassa enrollments only.</p>
            </div>

            <div class="px-6 py-4">
                {{-- Department -> Class -> Section narrow each other, the same
                     pattern the attendance and enrollment pages use. --}}
                <form
                    method="GET"
                    action="{{ route('prayer-attendance.index') }}"
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
                        </div>

                        <!-- Month -->
                        <div>
                            <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                            <select name="month" id="month"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @foreach($months as $number => $name)
                                    <option value="{{ $number }}" {{ $filters['month'] === $number ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Year -->
                        <div>
                            <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                            <select name="year" id="year"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @foreach($years as $year)
                                    <option value="{{ $year }}" {{ $filters['year'] === $year ? 'selected' : '' }}>{{ $year }}</option>
                                @endforeach
                            </select>
                            {{-- Past months are the normal case: the register
                                 is transcribed after the month has ended. --}}
                            <p class="mt-1 text-sm text-gray-500">Past months can be entered.</p>
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Load Prayer Attendance Sheet
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if($sheet === null)
            <div class="bg-white rounded-lg shadow-sm px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Choose a session, department and class to load the sheet.</h3>
                <p class="mt-1 text-sm text-gray-500">The section is optional &mdash; leaving it blank draws the whole class.</p>
            </div>
        @elseif($sheet->isEmpty())
            <div class="bg-white rounded-lg shadow-sm px-6 py-12 text-center">
                <h3 class="text-sm font-medium text-gray-900">No Madrassa students match these filters.</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Only students with an active Madrassa enrollment appear here. School students are not part of
                    prayer attendance.
                </p>
            </div>
        @else
            <form method="POST" action="{{ route('prayer-attendance.store') }}"
                x-data="{
                    cells: {{ Js::from($cells) }},
                    initial: {{ Js::from($initialCells) }},
                    students: {{ Js::from($studentNames) }},
                    dayLabels: {{ Js::from($dayLabels) }},
                    prayers: {{ Js::from($prayers) }},
                    reason: { open: false, key: null, student: '', day: '', prayer: '', value: '' },

                    cell(key) {
                        return this.cells[key] ?? { status: '', reason: '' }
                    },

                    /* Unmarked -> Present -> Absent -> Unmarked. A prayer can
                       be taken back to unmarked because a paper register can
                       be blank, and a mark typed against the wrong row has to
                       be removable. Nothing ever starts as Present. */
                    toggle(key) {
                        const current = this.cell(key).status

                        if (current === '') {
                            this.cells[key].status = 'Present'
                            this.cells[key].reason = ''
                            return
                        }

                        if (current === 'Present') {
                            this.cells[key].status = 'Absent'
                            return
                        }

                        // Absent -> Unmarked. The reason goes with the mark it
                        // explained rather than lingering on an empty cell.
                        this.cells[key].status = ''
                        this.cells[key].reason = ''
                    },

                    /* The reason is optional, so this is opened deliberately
                       rather than forced on every absence. */
                    openReason(key) {
                        if (this.cell(key).status !== 'Absent') return

                        const [enrollmentId, date, prayer] = key.split('|')

                        this.reason = {
                            open: true,
                            key: key,
                            student: this.students[enrollmentId] ?? '',
                            day: this.dayLabels[date] ?? date,
                            prayer: prayer,
                            value: this.cell(key).reason ?? '',
                        }

                        this.$nextTick(() => this.$refs.reasonInput?.focus())
                    },

                    saveReason() {
                        this.cells[this.reason.key].reason = this.reason.value.trim()
                        this.reason.open = false
                    },

                    boxClasses(key) {
                        const status = this.cell(key).status
                        if (status === 'Present') return 'bg-green-500 border-green-600 text-white hover:bg-green-600'
                        if (status === 'Absent') return 'bg-red-500 border-red-600 text-white hover:bg-red-600'
                        return 'bg-gray-100 border-gray-300 text-gray-500 hover:bg-gray-200'
                    },

                    cellTitle(key) {
                        const [enrollmentId, date, prayer] = key.split('|')
                        const cell = this.cell(key)
                        const status = cell.status || 'Unmarked'
                        const reason = cell.status === 'Absent' && cell.reason ? ' - ' + cell.reason : ''

                        return (this.students[enrollmentId] ?? '') + ', ' + (this.dayLabels[date] ?? date)
                            + ', ' + prayer + ': ' + status + reason
                            + (cell.status === 'Absent' ? ' (right-click for reason)' : '')
                    },

                    /* Only what the administrator actually changed is sent.
                       Prayers not reached yet stay unmarked, and rows already
                       on file are left alone unless they were edited. */
                    signature(cell) {
                        if (! cell) return ''
                        return cell.status === 'Absent' ? 'Absent|' + (cell.reason ?? '') : (cell.status ?? '')
                    },

                    get changedCells() {
                        return Object.keys(this.cells)
                            .filter(key => this.signature(this.cells[key]) !== this.signature(this.initial[key]))
                            .map(key => {
                                const [enrollmentId, date, prayer] = key.split('|')
                                const status = this.cells[key].status

                                return {
                                    student_academic_enrollment_id: Number(enrollmentId),
                                    student_id: Number(this.studentIds[enrollmentId]),
                                    attendance_date: date,
                                    prayer: prayer,
                                    /* An emptied cell asks the server to
                                       remove the row, which is what unmarking
                                       actually means. */
                                    status: status === '' ? 'Unmarked' : status,
                                    absence_reason: status === 'Absent' ? (this.cells[key].reason || null) : null,
                                }
                            })
                    },

                    studentIds: {{ Js::from($sheet->mapWithKeys(fn ($enrollment) => [$enrollment->id => $enrollment->student_id])->all()) }},

                    get changedCount() { return this.changedCells.length },

                    /* Every counter in one pass over the cells rather than one
                       pass each: the month holds students x days x five. */
                    get counts() {
                        const totals = { Present: 0, Absent: 0, Unmarked: 0, byPrayer: {} }

                        this.prayers.forEach(prayer => {
                            totals.byPrayer[prayer] = { Present: 0, Absent: 0, Unmarked: 0 }
                        })

                        Object.keys(this.cells).forEach(key => {
                            const prayer = key.split('|')[2]
                            const status = this.cells[key].status || 'Unmarked'

                            totals[status]++

                            if (totals.byPrayer[prayer]) {
                                totals.byPrayer[prayer][status]++
                            }
                        })

                        return totals
                    },

                    resetChanges() {
                        this.cells = JSON.parse(JSON.stringify(this.initial))
                        this.reason.open = false
                    },
                }"
                @submit="$refs.payload.value = JSON.stringify(changedCells)">
                @csrf

                {{-- The group and month the sheet was drawn for. Re-checked
                     against every submitted row on the server; carried here
                     so the save knows which sheet it is saving, not so it can
                     be trusted. --}}
                @foreach($sheetParameters as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach

                {{-- The month arrives as one JSON field: a class of thirty
                     over a full month is thousands of cells once the five
                     prayers are counted, which would run past PHP's
                     max_input_vars as individual inputs. --}}
                <input type="hidden" name="sheet" x-ref="payload" value="">

                <div class="bg-white rounded-lg shadow-sm">
                    <!-- Sheet heading -->
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">
                            {{ collect([
                                $group['department']?->name,
                                $group['academicClass']?->name,
                                $group['section'] ? 'Section ' . $group['section']->name : 'All sections',
                            ])->filter()->implode(' - ') }}
                        </h3>
                        <div class="mt-1 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                            <span class="font-medium text-gray-800">{{ $monthLabel }}</span>
                            <span>Session: {{ $group['session']?->name ?? '—' }}</span>
                            <span>Prayer days: {{ $summary['prayer_days'] }}</span>
                            <span>Students: {{ $summary['students'] }}</span>
                        </div>
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

                    <!-- Overall counters -->
                    <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-4 text-sm">
                            <span class="text-gray-500">Students: <span class="font-semibold text-gray-900">{{ $summary['students'] }}</span></span>
                            <span class="inline-flex items-center">
                                <span class="w-3 h-3 rounded bg-green-500 mr-2"></span>
                                Present: <span class="font-semibold ml-1" x-text="counts.Present"></span>
                            </span>
                            <span class="inline-flex items-center">
                                <span class="w-3 h-3 rounded bg-red-500 mr-2"></span>
                                Absent: <span class="font-semibold ml-1" x-text="counts.Absent"></span>
                            </span>
                            <span class="inline-flex items-center">
                                <span class="w-3 h-3 rounded bg-gray-200 border border-gray-300 mr-2"></span>
                                Unmarked: <span class="font-semibold ml-1" x-text="counts.Unmarked"></span>
                            </span>
                            <span class="text-gray-500">Off days excluded</span>
                        </div>

                        <button type="button" @click="resetChanges()"
                            :disabled="changedCount === 0"
                            class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            Reset unsaved changes
                        </button>
                    </div>

                    <!-- Per-prayer counters -->
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                            @foreach($prayers as $prayer)
                                <div class="border border-gray-200 rounded-lg p-3">
                                    <p class="text-sm font-semibold text-gray-800">
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded bg-gray-100 text-gray-700 text-xs mr-1">{{ $prayerInitials[$prayer] }}</span>
                                        {{ $prayer }}
                                    </p>
                                    <div class="mt-2 space-y-1 text-sm">
                                        <p class="text-green-700">Present: <span class="font-semibold" x-text="counts.byPrayer['{{ $prayer }}'].Present"></span></p>
                                        <p class="text-red-700">Absent: <span class="font-semibold" x-text="counts.byPrayer['{{ $prayer }}'].Absent"></span></p>
                                        <p class="text-gray-500">Unmarked: <span class="font-semibold" x-text="counts.byPrayer['{{ $prayer }}'].Unmarked"></span></p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- How to use the grid -->
                    <div class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-sm text-gray-600">
                        Click a prayer to cycle it: <span class="font-medium">Unmarked &rarr; Present &rarr; Absent &rarr; Unmarked</span>.
                        Right-click an Absent prayer to add an optional reason.
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
                                            class="px-1 py-2 text-center text-xs font-medium border-b border-r border-gray-200 {{ $day['is_off_day'] ? 'bg-amber-50 text-amber-700' : 'text-gray-500' }}">
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
                                            {{-- Read only, and a different month:
                                                 the whole prayer history for this
                                                 student, entry stays here. --}}
                                            <a href="{{ route('students.prayer-attendance', $enrollment->student_id) }}"
                                               class="ml-6 text-xs text-blue-600 hover:text-blue-800">
                                                Prayer history
                                            </a>
                                        </td>

                                        @foreach($days as $day)
                                            @if($day['is_off_day'])
                                                {{-- Not a cell at all: no buttons, nothing to
                                                     submit, and the backend refuses the date
                                                     even if one were forged. --}}
                                                <td class="bg-amber-50 border-b border-r border-gray-200 px-1 py-2 text-center text-xs text-amber-600 align-middle">
                                                    OFF
                                                </td>
                                            @else
                                                <td class="border-b border-r border-gray-200 px-1 py-2 align-middle">
                                                    {{-- Five buttons, one per prayer, in the
                                                         order they are prayed. --}}
                                                    <div class="flex flex-col items-center gap-0.5">
                                                        @foreach($prayers as $prayer)
                                                            @php($key = StudentPrayerAttendance::cellKey($enrollment->id, $day['date'], $prayer))
                                                            <button type="button"
                                                                @click="toggle('{{ $key }}')"
                                                                @contextmenu.prevent="openReason('{{ $key }}')"
                                                                :class="boxClasses('{{ $key }}')"
                                                                :title="cellTitle('{{ $key }}')"
                                                                class="w-6 h-5 rounded border text-[10px] font-semibold leading-none transition-colors focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-blue-500">
                                                                {{ $prayerInitials[$prayer] }}
                                                            </button>
                                                        @endforeach
                                                    </div>
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
                            Save Prayer Attendance
                        </button>
                        <p class="text-sm text-gray-600">
                            <span x-text="changedCount"></span> unsaved change(s).
                            A month can be entered over several sittings; unmarked prayers are left as they are.
                        </p>
                    </div>
                </div>

                <!-- Absence reason -->
                <div x-show="reason.open" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-900 bg-opacity-50" @click="reason.open = false"></div>

                    <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md p-6"
                         @keydown.escape.window="reason.open && (reason.open = false)">
                        <h3 class="text-lg font-semibold text-gray-800">Reason for absence</h3>
                        <p class="text-sm text-gray-600 mt-1">
                            <span class="font-medium" x-text="reason.student"></span>
                            &middot; <span x-text="reason.day"></span>
                            &middot; <span x-text="reason.prayer"></span>
                        </p>

                        <div class="mt-4">
                            <label for="prayer-absence-reason" class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                            <input type="text" id="prayer-absence-reason"
                                x-ref="reasonInput"
                                x-model="reason.value"
                                @keydown.enter.prevent="saveReason()"
                                list="prayer-absence-reasons"
                                placeholder="Sick, family issue, emergency..."
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            {{-- Optional, unlike the academic register: a paper
                                 prayer sheet often records only the mark. --}}
                            <p class="mt-1 text-sm text-gray-500">Optional. Leave it blank to record the absence without a reason.</p>
                        </div>

                        <div class="mt-6 flex justify-end gap-2">
                            <button type="button" @click="reason.open = false"
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="button" @click="saveReason()"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Save Reason
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Suggestions only: the reason is free text so an administrator
                 can record what actually happened. --}}
            <datalist id="prayer-absence-reasons">
                <option value="Sick"></option>
                <option value="Family issue"></option>
                <option value="Emergency"></option>
                <option value="Personal reason"></option>
                <option value="Other"></option>
            </datalist>
        @endif
    </div>
</x-layout.admin>
