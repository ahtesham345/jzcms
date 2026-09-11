<x-layout.admin title="Computer Course">
    <x-slot name="header">
        Computer Course
    </x-slot>

    @if(session('success'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
            <p class="text-sm text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    @if($course === null)
        {{-- The course is created with the Computer department. Said plainly
             rather than shown as an empty table, because an empty table looks
             like a course with no semesters. --}}
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800">No Computer course has been set up</h3>
            <p class="text-sm text-gray-600 mt-2">
                The Computer department and its course are created by the Computer course seeder.
                Run <code class="px-1 py-0.5 bg-gray-100 rounded text-xs">php artisan db:seed --class=ComputerCourseSeeder</code>
                to add them, then the six semesters can be dated here.
            </p>
        </div>
    @else
        <!-- Course Summary -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">{{ $course->name }}</h3>
                    <div class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                        <span>Duration: <span class="font-medium text-gray-800">{{ $course->duration_years }} {{ \Illuminate\Support\Str::plural('Year', $course->duration_years) }}</span></span>
                        <span>Semesters: <span class="font-medium text-gray-800">{{ $course->semester_count }}</span></span>
                        <span>
                            Status:
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $course->status ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $course->status ? 'Active' : 'Inactive' }}
                            </span>
                        </span>
                    </div>
                    @if($course->description)
                        <p class="mt-2 text-sm text-gray-600">{{ $course->description }}</p>
                    @endif
                </div>
                <a href="{{ route('computer-course.edit', $course->id) }}"
                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit Course
                </a>
            </div>
        </div>

        <!-- Semesters -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Semesters</h3>
                <p class="text-sm text-gray-600 mt-1">
                    The stages of the course, in teaching order. Set each one's dates and what will be taught.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semester</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">What Will Be Taught</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($semesters as $semester)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $semester->order }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $semester->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    {{ $semester->start_date?->format('d M, Y') ?? '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    {{ $semester->end_date?->format('d M, Y') ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 max-w-md">
                                    @if(filled($semester->curriculum))
                                        {{-- The admin's own lines, kept as lines. --}}
                                        <div class="whitespace-pre-line">{{ $semester->curriculum }}</div>
                                    @else
                                        <span class="text-gray-400">Not set yet</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $semester->status ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $semester->status ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <a href="{{ route('computer-course.semesters.edit', $semester->id) }}"
                                       class="text-blue-600 hover:text-blue-900">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">
                                    This course has no semesters yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layout.admin>
