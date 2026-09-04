@php
    // The heading names the group from whatever was drilled into. A group
    // filtered only by track is still a valid, if broad, group.
    $groupLabel = collect([
        $track,
        $academicClass?->name ?? $department?->name,
        $section ? 'Section ' . $section->name : null,
    ])->filter()->implode(' - ');

    // The parameters that define this group, carried through search and
    // pagination so the drill-down is never lost.
    $groupParameters = array_filter([
        'session' => request('session'),
        'track' => request('track'),
        'department' => request('department'),
        'class' => request('class'),
        'section' => request('section'),
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<x-layout.admin title="Academic Group">
    <x-slot name="header">
        Academic Group
    </x-slot>

    <div class="space-y-6">
        <!-- Back Button -->
        <div>
            <a href="{{ route('academics.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Academic Management
            </a>
        </div>

        <!-- Group heading -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-xl font-semibold text-gray-800">
                {{ $groupLabel !== '' ? $groupLabel : 'All Academic Records' }}
            </h2>
            <p class="text-sm text-gray-600 mt-1">
                Academic Session: {{ $session?->name ?? 'All sessions' }}
            </p>
        </div>

        <!-- Group summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach([
                ['label' => 'Total Students', 'value' => $statistics['total'], 'color' => 'blue'],
                ['label' => 'Active Students', 'value' => $statistics['active'], 'color' => 'green'],
                ['label' => 'Completed Students', 'value' => $statistics['completed'], 'color' => 'purple'],
                ['label' => 'Left Students', 'value' => $statistics['left'], 'color' => 'yellow'],
            ] as $card)
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <p class="text-sm font-medium text-gray-600 mb-1">{{ $card['label'] }}</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $card['value'] }}</p>
                    <p class="text-sm text-gray-600 mt-2">In this group</p>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-lg shadow-sm">
            <!-- Search within the group -->
            <div class="px-6 py-4 border-b border-gray-200">
                <form method="GET" action="{{ route('academics.groups') }}" class="space-y-4">
                    {{-- The group itself travels with the search, so
                         searching never drops the drill-down. --}}
                    @foreach($groupParameters as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
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

                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Enrollment Status</label>
                            <select name="status" id="status"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All Statuses</option>
                                @foreach($enrollmentStatuses as $statusOption)
                                    <option value="{{ $statusOption }}" {{ $status === $statusOption ? 'selected' : '' }}>{{ $statusOption }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            Search
                        </button>
                        @if(request()->filled('search') || $status !== 'Active')
                            <a href="{{ route('academics.groups', $groupParameters) }}"
                               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                Clear Search
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Table Header -->
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Students</h3>
                @if($records->total() > 0)
                    <p class="text-sm text-gray-600 mt-1">
                        Showing {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }} students
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
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No.</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Type</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enrollment Date</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
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
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student->registration_number }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student->roll_number ?? '—' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student->student_type }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ $record->start_date?->format('d M, Y') ?? 'N/A' }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $record->statusBadgeClasses() }}">
                                            {{ $record->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                        {{-- All existing routes; nothing is
                                             reimplemented here. --}}
                                        <div x-data="{ open: false }" class="relative">
                                            <button type="button" @click="open = ! open" @click.away="open = false"
                                                    class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                                Actions
                                                <svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>

                                            <div x-show="open" x-cloak
                                                 class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 ring-1 ring-black ring-opacity-5 z-40">
                                                <a href="{{ route('academics.enrollments.show', $record->id) }}"
                                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    View Academic Record
                                                </a>
                                                <a href="{{ route('students.show', $student->id) }}"
                                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    View Student
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No academic records found.</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        No students match this academic group and search.
                    </p>
                    <div class="mt-6">
                        <a href="{{ route('academics.index') }}"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Back to Academic Management
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-layout.admin>
