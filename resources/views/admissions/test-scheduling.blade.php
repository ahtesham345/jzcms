<x-layout.admin title="Test Scheduling">
    <x-slot name="header">
        Test Scheduling
    </x-slot>

    <div
        x-data="{
            confirmOpen: false,
            studentType: '{{ old('student_type') }}',
            testDate: '{{ old('test_date') }}',
            testTime: '{{ old('test_time') }}',
            counts: {{ Js::from($eligibleCounts) }},
            get eligible() { return this.counts[this.studentType] ?? 0 },
            open() {
                if (!this.studentType || !this.testDate || !this.testTime) {
                    this.$refs.form.reportValidity()
                    return
                }
                this.confirmOpen = true
            },
        }"
        class="space-y-6"
    >
        <!-- Scheduling form -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Schedule Tests by Student Type</h3>
                <p class="text-sm text-gray-600 mt-1">
                    Sets the test date and time for every Pending or Under Review application of the
                    chosen student type and moves them to Test Scheduled. Applications that are already
                    scheduled, completed, passed, failed, approved or rejected are left untouched, and
                    test results, marks and remarks are never changed.
                </p>
            </div>

            <form
                method="POST"
                action="{{ route('admissions.test-scheduling.store') }}"
                class="p-6"
                x-ref="form"
            >
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Student Type -->
                    <div>
                        <label for="student_type" class="block text-sm font-medium text-gray-700 mb-1">
                            Student Type <span class="text-red-500">*</span>
                        </label>
                        <select
                            name="student_type"
                            id="student_type"
                            x-model="studentType"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('student_type') border-red-500 @enderror"
                        >
                            <option value="">Select Student Type</option>
                            @foreach(\App\Models\AdmissionApplication::STUDENT_TYPES as $type)
                                <option value="{{ $type }}">
                                    {{ $type }} ({{ $eligibleCounts[$type] }} eligible)
                                </option>
                            @endforeach
                        </select>
                        @error('student_type')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Test Date -->
                    <div>
                        <label for="test_date" class="block text-sm font-medium text-gray-700 mb-1">
                            Test Date <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            name="test_date"
                            id="test_date"
                            x-model="testDate"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('test_date') border-red-500 @enderror"
                        >
                        @error('test_date')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Test Time -->
                    <div>
                        <label for="test_time" class="block text-sm font-medium text-gray-700 mb-1">
                            Test Time <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="time"
                            name="test_time"
                            id="test_time"
                            x-model="testTime"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('test_time') border-red-500 @enderror"
                        >
                        @error('test_time')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Live eligible count for the chosen type -->
                <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg" x-show="studentType" x-cloak>
                    <p class="text-sm text-blue-800">
                        <span class="font-semibold" x-text="eligible"></span>
                        <span x-text="eligible === 1 ? 'application is' : 'applications are'"></span>
                        currently eligible for
                        <span class="font-medium" x-text="studentType"></span>.
                    </p>
                </div>

                <div class="flex items-center justify-end space-x-3 mt-6 pt-6 border-t border-gray-200">
                    <a
                        href="{{ route('admissions.index') }}"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        Back to Applications
                    </a>
                    <button
                        type="button"
                        @click="open()"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        Schedule Tests
                    </button>
                </div>

                <!-- Confirmation modal -->
                <div
                    x-show="confirmOpen"
                    x-cloak
                    class="fixed inset-0 z-50 overflow-y-auto"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="bulk-schedule-title"
                >
                    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                        <div
                            x-show="confirmOpen"
                            x-transition:enter="ease-out duration-300"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="ease-in duration-200"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            @click="confirmOpen = false"
                            class="fixed inset-0 bg-gray-900 bg-opacity-50 transition-opacity"
                        ></div>

                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                        <div
                            x-show="confirmOpen"
                            x-transition:enter="ease-out duration-300"
                            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                            x-transition:leave="ease-in duration-200"
                            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                            @click.stop
                            class="inline-block w-full max-w-lg my-8 text-left align-middle bg-white rounded-lg shadow-xl transform transition-all"
                        >
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 id="bulk-schedule-title" class="text-lg font-semibold text-gray-800">
                                    Confirm Test Scheduling
                                </h3>
                            </div>

                            <div class="px-6 py-4">
                                <template x-if="eligible > 0">
                                    <p class="text-sm text-gray-700">
                                        This will schedule the admission test for
                                        <span class="font-semibold" x-text="eligible"></span>
                                        <span class="font-medium" x-text="studentType"></span>
                                        <span x-text="eligible === 1 ? 'application' : 'applications'"></span>
                                        on <span class="font-medium" x-text="testDate"></span>
                                        at <span class="font-medium" x-text="testTime"></span>,
                                        and move them to Test Scheduled.
                                    </p>
                                </template>

                                <template x-if="eligible === 0">
                                    <p class="text-sm text-gray-700">
                                        There are no Pending or Under Review
                                        <span class="font-medium" x-text="studentType"></span>
                                        applications, so nothing will be scheduled.
                                    </p>
                                </template>

                                <p class="mt-3 text-sm text-gray-500">
                                    Applications that already have a test scheduled or have reached an
                                    outcome are not affected. Test results, marks and remarks are unchanged.
                                </p>
                            </div>

                            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end space-x-3">
                                <button
                                    type="button"
                                    @click="confirmOpen = false"
                                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-white transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="eligible === 0"
                                    :class="eligible === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-700'"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg transition-colors"
                                >
                                    Confirm &amp; Schedule
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        @if($justScheduled->isNotEmpty())
            <!-- WhatsApp follow up for the applications just scheduled -->
            <div class="bg-white rounded-lg shadow-sm border-l-4 border-green-500">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-800">
                        Notify Parents on WhatsApp ({{ $justScheduled->count() }})
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">
                        These applications were just scheduled. Each button opens WhatsApp with the
                        test details pre-filled &mdash; nothing is sent until you press Send in WhatsApp.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Application No</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Father Mobile</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Test Schedule</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">WhatsApp</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($justScheduled as $application)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <a href="{{ route('admissions.show', $application->id) }}" class="text-blue-600 hover:text-blue-800">
                                            {{ $application->application_number }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $application->student_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $application->father_mobile }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $application->formattedTestSchedule() }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($application->whatsappUrl())
                                            <x-whatsapp-button :application="$application" compact />
                                        @else
                                            <span class="text-sm text-gray-400">No valid mobile</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Eligible applications by type -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-base font-semibold text-gray-800">Applications Awaiting a Test</h3>
                <p class="text-sm text-gray-600 mt-1">Pending and Under Review applications, by student type.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Student Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Eligible Applications
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach(\App\Models\AdmissionApplication::STUDENT_TYPES as $type)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                                        {{ $type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $eligibleCounts[$type] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layout.admin>
