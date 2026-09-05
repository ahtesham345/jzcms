@php
    // Read only throughout. Prayers are entered and corrected on the
    // monthly sheet, which is the module's only writer - there is no edit,
    // delete or mark action anywhere on this page.
    $activeFilters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
@endphp

<x-layout.admin title="Prayer Attendance History">
    <x-slot name="header">
        Prayer Attendance History
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('students.show', $student->id) }}#prayer-attendance" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Student Profile
            </a>

            {{-- The only way to change anything is the entry sheet, so this
                 is the one action offered. --}}
            <a href="{{ route('prayer-attendance.index', array_filter([
                    'academic_session_id' => $currentEnrollment?->academic_session_id,
                    'department_id' => $currentEnrollment?->department_id,
                    'academic_class_id' => $currentEnrollment?->academic_class_id,
                    'section_id' => $currentEnrollment?->section_id,
               ])) }}"
               class="inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                </svg>
                Prayer Attendance Monthly Sheet
            </a>
        </div>

        <!-- Student -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="h-20 w-20 rounded-full bg-gray-300 flex items-center justify-center overflow-hidden flex-shrink-0">
                        @if($student->photo && \Storage::disk('public')->exists($student->photo))
                            <img src="{{ asset('storage/' . $student->photo) }}" class="h-20 w-20 rounded-full object-cover" alt="{{ $student->full_name }}">
                        @else
                            <span class="text-2xl font-medium text-gray-700">{{ mb_strtoupper(mb_substr($student->full_name, 0, 1)) }}</span>
                        @endif
                    </div>

                    <div>
                        <h2 class="text-xl font-semibold text-gray-800">{{ $student->full_name }}</h2>
                        <p class="text-sm text-gray-600 mt-1">
                            {{ $student->registration_number }}
                            @if($student->roll_number) &middot; Roll No. {{ $student->roll_number }} @endif
                        </p>
                    </div>
                </div>

                @if($hasMadrassaEnrollment)
                    <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-emerald-100 text-emerald-800">
                        Madrassa
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mt-6 border-t border-gray-200 pt-6">
                <div>
                    <label class="text-sm font-medium text-gray-500">Current Programme</label>
                    <p class="text-gray-900">{{ $student->student_type }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Current Session</label>
                    <p class="text-gray-900">{{ $currentEnrollment?->academicSession?->name ?? 'No active placement' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Current Department</label>
                    <p class="text-gray-900">{{ $currentEnrollment?->department?->name ?? '—' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Current Class</label>
                    <p class="text-gray-900">{{ $currentEnrollment?->academicClass?->name ?? '—' }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Current Section</label>
                    <p class="text-gray-900">{{ $currentEnrollment?->section?->name ?? ($currentEnrollment ? 'No section' : '—') }}</p>
                </div>
            </div>

            {{-- Said plainly: the placement above is where the student is
                 now. Every record below keeps the placement it was made
                 under. --}}
            <p class="mt-4 text-sm text-gray-500">
                The placement above is the student's current one. Each prayer below shows the session, department,
                class and section it was recorded under.
                @if($enrollmentCount > 1)
                    This student has held {{ $enrollmentCount }} Madrassa placements; all of them are included.
                @endif
            </p>
        </div>

        @if(! $hasMadrassaEnrollment)
            {{-- No madrassa enrollment means no prayer register. The page
                 says so rather than showing an empty table that might be
                 read as "this student never prayed". Nothing about a school
                 enrollment is read, here or anywhere on this page. --}}
            <div class="bg-white rounded-lg shadow-sm px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">This student has no Madrassa enrollment.</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Prayer attendance is kept for Madrassa students only. School enrollments have no prayer register.
                </p>
            </div>
        @else
            <!-- Summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Recorded</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $summary['total'] }}</p>
                    <p class="text-sm text-gray-600 mt-2">Prayers on file</p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-600 mb-1">Present</p>
                    <p class="text-3xl font-bold text-green-700">{{ $summary['present'] }}</p>
                    <p class="text-sm text-gray-600 mt-2">Across all five prayers</p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-600 mb-1">Absent</p>
                    <p class="text-3xl font-bold text-red-700">{{ $summary['absent'] }}</p>
                    <p class="text-sm text-gray-600 mt-2">Across all five prayers</p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Per Prayer</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    {{-- In the order they are prayed, never alphabetically. --}}
                    @foreach($prayers as $prayer)
                        <div class="border border-gray-200 rounded-lg p-3">
                            <p class="text-sm font-semibold text-gray-800">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded bg-gray-100 text-gray-700 text-xs mr-1">{{ $prayerInitials[$prayer] }}</span>
                                {{ $prayer }}
                            </p>
                            <div class="mt-2 space-y-1 text-sm">
                                <p class="text-green-700">Present: <span class="font-semibold">{{ $summary['by_prayer'][$prayer]['present'] }}</span></p>
                                <p class="text-red-700">Absent: <span class="font-semibold">{{ $summary['by_prayer'][$prayer]['absent'] }}</span></p>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Counts of what was recorded. A prayer nobody transcribed
                     is in neither column, and a weekend has no row at all. --}}
                <p class="mt-4 text-sm text-gray-500">
                    Counts of recorded prayers only. Unmarked prayers and weekends are not counted as either.
                    This panel covers all five prayers whatever the prayer filter below shows.
                </p>
            </div>

            <div class="bg-white rounded-lg shadow-sm">
                <!-- Filters -->
                <div class="px-6 py-4 border-b border-gray-200">
                    <form method="GET" action="{{ route('students.prayer-attendance', $student->id) }}" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label for="academic_session_id" class="block text-sm font-medium text-gray-700 mb-1">Academic Session</label>
                                <select name="academic_session_id" id="academic_session_id"
                                    class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">All Sessions</option>
                                    {{-- Only the sessions this student holds a
                                         Madrassa enrollment in. --}}
                                    @foreach($academicSessions as $session)
                                        <option value="{{ $session->id }}" {{ $filters['academic_session_id'] === $session->id ? 'selected' : '' }}>
                                            {{ $session->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                                <select name="month" id="month"
                                    class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">All Months</option>
                                    @foreach($months as $number => $name)
                                        <option value="{{ $number }}" {{ $filters['month'] === $number ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                                <select name="year" id="year"
                                    class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">All Years</option>
                                    @foreach($years as $year)
                                        <option value="{{ $year }}" {{ $filters['year'] === $year ? 'selected' : '' }}>{{ $year }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="prayer" class="block text-sm font-medium text-gray-700 mb-1">Prayer</label>
                                <select name="prayer" id="prayer"
                                    class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">All Prayers</option>
                                    @foreach($prayers as $prayer)
                                        <option value="{{ $prayer }}" {{ $filters['prayer'] === $prayer ? 'selected' : '' }}>{{ $prayer }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Apply Filters
                            </button>
                            @if($activeFilters !== [])
                                <a href="{{ route('students.prayer-attendance', $student->id) }}"
                                   class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                    Clear Filters
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                <!-- Monthly at a glance -->
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">{{ $gridMonthLabel }} at a Glance</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        @if($gridIsDefaulted)
                            The most recent month with records.
                        @else
                            The selected month.
                        @endif
                        All five prayers, whatever the prayer filter shows below.
                    </p>

                    <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-gray-600">
                        <span class="inline-flex items-center"><span class="w-3 h-3 rounded bg-green-500 mr-2"></span>Present</span>
                        <span class="inline-flex items-center"><span class="w-3 h-3 rounded bg-red-500 mr-2"></span>Absent</span>
                        <span class="inline-flex items-center"><span class="w-3 h-3 rounded bg-gray-200 border border-gray-300 mr-2"></span>Unmarked</span>
                        <span class="inline-flex items-center"><span class="px-1 rounded bg-amber-50 text-amber-700 text-xs mr-2">OFF</span>Sunday</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Day</th>
                                @foreach($prayers as $prayer)
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $prayer }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($grid as $row)
                                <tr class="{{ $row['is_off_day'] ? 'bg-amber-50' : 'hover:bg-gray-50' }}">
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{{ $row['label'] }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">{{ $row['weekday'] }}</td>

                                    @foreach($prayers as $prayer)
                                        @if($row['is_off_day'])
                                            {{-- Never a status. A weekend is not an
                                                 absence, and no row exists for it. --}}
                                            <td class="px-4 py-2 text-center text-xs font-medium text-amber-700">OFF</td>
                                        @else
                                            @php($cell = $row['cells'][$prayer])
                                            <td class="px-4 py-2 text-center">
                                                @if($cell['status'] === 'Present')
                                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-green-500 text-white text-xs font-semibold"
                                                          title="{{ $prayer }}: Present">&check;</span>
                                                @elseif($cell['status'] === 'Absent')
                                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-red-500 text-white text-xs font-semibold"
                                                          title="{{ $prayer }}: Absent{{ $cell['reason'] ? ' - ' . $cell['reason'] : '' }}">&times;</span>
                                                @else
                                                    {{-- No record on file. Not an absence:
                                                         nobody has transcribed it yet. --}}
                                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-gray-200 border border-gray-300"
                                                          title="{{ $prayer }}: Unmarked"></span>
                                                @endif
                                            </td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Detailed history -->
                <div class="px-6 py-4 border-y border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Prayer Records</h3>
                    @if($records->total() > 0)
                        <p class="text-sm text-gray-600 mt-1">
                            Showing {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }} prayer records
                        </p>
                    @endif
                </div>

                @if($records->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Day</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Session</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prayer</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Absence Reason</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                {{-- Newest day first, and within a day the five
                                     prayers in the order they are prayed. The
                                     session, department, class and section all
                                     come from the enrollment each record was
                                     written against, so a prayer recorded before
                                     a promotion still names the placement it was
                                     made under. --}}
                                @foreach($records as $record)
                                    @php($enrollment = $record->studentAcademicEnrollment)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $record->attendance_date->format('d M, Y') }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">{{ $record->attendance_date->format('D') }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment?->academicSession?->name ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment?->department?->name ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment?->academicClass?->name ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $enrollment?->section?->name ?? 'No section' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $record->prayer }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $record->statusBadgeClasses() }}">
                                                {{ $record->status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-700 max-w-xs">
                                            @if($record->isPresent())
                                                {{-- A reason only ever describes an
                                                     absence, so a Present row shows
                                                     none - the save clears it too. --}}
                                                <span class="text-gray-400">&mdash;</span>
                                            @elseif($record->absence_reason)
                                                {{ $record->absence_reason }}
                                            @else
                                                {{-- Nothing is invented for an absence
                                                     the register left blank. --}}
                                                <span class="text-gray-400">No reason provided</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200">
                        {{-- Keeps every active filter across pages. --}}
                        {{ $records->links() }}
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No prayer records found.</h3>
                        @if($activeFilters !== [])
                            <p class="mt-1 text-sm text-gray-500">Try clearing the filters or choosing another month.</p>
                            <div class="mt-6">
                                <a href="{{ route('students.prayer-attendance', $student->id) }}"
                                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Clear all filters
                                </a>
                            </div>
                        @else
                            <p class="mt-1 text-sm text-gray-500">
                                Prayer attendance is entered on the monthly sheet, one class and one month at a time.
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-layout.admin>
