@php
    // What the student currently holds, per track, for the summary panel.
    $activeByTrack = $student->activeAcademicEnrollments->mapWithKeys(fn ($enrollment) => [
        $enrollment->academic_track => [
            'session' => $enrollment->academicSession?->name ?? 'N/A',
            'department' => $enrollment->department?->name ?? 'N/A',
            'class' => $enrollment->academicClass?->name ?? 'N/A',
            'section' => $enrollment->section?->name ?? 'No section',
            'start_date' => $enrollment->start_date?->format('d M, Y') ?? 'N/A',
        ],
    ]);

    // Flat id => name maps so the summary can name the target selections.
    $classNames = $classesByDepartment->flatten(1)->pluck('name', 'id');
    $sectionNames = $sectionsByClass->flatten(1)->pluck('name', 'id');
@endphp

<x-layout.admin title="Promote Student">
    <x-slot name="header">
        Promote Student
    </x-slot>

    <div class="max-w-4xl">
        <!-- Back Button -->
        <div class="mb-4">
            <a href="{{ route('students.show', $student->id) }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Student Profile
            </a>
        </div>

        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    {{ $student->full_name }} — {{ $student->registration_number }}
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    Promotion moves one track. The current enrollment is completed and kept in the
                    academic history; a new active enrollment is created for the target session.
                </p>
            </div>

            @if($activeByTrack->isEmpty())
                <div class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No active enrollment</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        This student has nothing to be promoted from. Add an academic enrollment first.
                    </p>
                    <div class="mt-6">
                        <a href="{{ route('students.show', $student->id) }}"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Back to Student Profile
                        </a>
                    </div>
                </div>
            @else
                <form
                    method="POST"
                    action="{{ route('students.promote.store', $student->id) }}"
                    class="p-6 space-y-6"
                    x-data="{
                        track: '{{ old('academic_track', $activeByTrack->keys()->first()) }}',
                        sessionId: '{{ old('academic_session_id') }}',
                        departmentId: '{{ old('department_id') }}',
                        academicClassId: '{{ old('academic_class_id') }}',
                        sectionId: '{{ old('section_id') }}',
                        activeByTrack: {{ Js::from($activeByTrack) }},
                        classesByDepartment: {{ Js::from($classesByDepartment) }},
                        sectionsByClass: {{ Js::from($sectionsByClass) }},
                        sessionNames: {{ Js::from($academicSessions->pluck('name', 'id')) }},
                        departmentNames: {{ Js::from($departments->pluck('name', 'id')) }},
                        classNames: {{ Js::from($classNames) }},
                        sectionNames: {{ Js::from($sectionNames) }},
                        get current() { return this.activeByTrack[this.track] ?? null },
                        get classes() {
                            return this.departmentId ? (this.classesByDepartment[this.departmentId] ?? []) : []
                        },
                        get sections() {
                            return this.academicClassId ? (this.sectionsByClass[this.academicClassId] ?? []) : []
                        },
                        get targetSession() { return this.sessionNames[this.sessionId] ?? '—' },
                        get targetClass() { return this.classNames[this.academicClassId] ?? '—' },
                        get targetSection() { return this.sectionNames[this.sectionId] ?? 'No section' },
                        get ready() { return !! (this.sessionId && this.departmentId && this.academicClassId) },
                        onDepartmentChange() {
                            // Drop selections that no longer belong to the department.
                            this.academicClassId = ''
                            this.sectionId = ''
                        },
                        onClassChange() { this.sectionId = '' },
                        confirmPromotion(event) {
                            const summary = `Promote the ${this.track} track?\n\n`
                                + `From: ${this.current.session} / ${this.current.class} / ${this.current.section}\n`
                                + `To:   ${this.targetSession} / ${this.targetClass} / ${this.targetSection}\n\n`
                                + `The current enrollment will be marked Completed and kept in the academic history.`

                            if (! window.confirm(summary)) { event.preventDefault() }
                        },
                    }"
                    @submit="confirmPromotion($event)"
                >
                    @csrf

                    <!-- Track -->
                    <div>
                        <label for="academic_track" class="block text-sm font-medium text-gray-700 mb-1">
                            Academic Track <span class="text-red-500">*</span>
                        </label>
                        <select name="academic_track" id="academic_track" required x-model="track"
                            class="w-full md:w-1/2 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('academic_track') border-red-500 @enderror">
                            @foreach($activeByTrack->keys() as $track)
                                <option value="{{ $track }}">{{ $track }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-sm text-gray-500">
                            Only tracks with an active enrollment can be promoted. Each track is promoted on its own.
                        </p>
                        @error('academic_track')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Current -> Target summary -->
                    <div class="border border-blue-200 bg-blue-50 rounded-lg p-4">
                        <h4 class="text-base font-semibold text-gray-800 mb-3">
                            Confirm the promotion
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Current</label>
                                <p class="text-gray-900 font-medium" x-text="current ? `${current.session} → ${current.class} → ${current.section}` : '—'"></p>
                                <p class="text-sm text-gray-500 mt-1">
                                    <span x-text="current ? current.department : ''"></span> department,
                                    enrolled <span x-text="current ? current.start_date : ''"></span>
                                </p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Will become</label>
                                <p class="text-gray-900 font-medium" x-show="ready" x-cloak
                                   x-text="`${targetSession} → ${targetClass} → ${targetSection}`"></p>
                                <p class="text-gray-500" x-show="! ready">Choose the target session, department and class below.</p>
                                <p class="text-sm text-gray-500 mt-1">
                                    for the <span class="font-medium" x-text="track"></span> track
                                </p>
                            </div>
                        </div>
                        <p class="mt-3 text-sm text-gray-600">
                            The current enrollment is marked <span class="font-medium">Completed</span> with the promotion
                            date as its completion date. It stays in the academic history.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Target Session -->
                        <div>
                            <label for="academic_session_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Target Academic Session <span class="text-red-500">*</span>
                            </label>
                            <select name="academic_session_id" id="academic_session_id" required x-model="sessionId"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('academic_session_id') border-red-500 @enderror">
                                <option value="">Select Session</option>
                                @foreach($academicSessions as $session)
                                    <option value="{{ $session->id }}">{{ $session->name }}</option>
                                @endforeach
                            </select>
                            {{-- Spelt out because the right answer differs by
                                 track, and the session already running is the
                                 one an Imam promoting a finished stage wants. --}}
                            <p class="mt-1 text-sm text-gray-500">
                                A madrassa stage can be completed part way through a session, so the session
                                already running is a valid choice. A school class runs for the whole year, so
                                the school track moves on to the next session.
                            </p>
                            @error('academic_session_id')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Promotion Date -->
                        <div>
                            <label for="promotion_date" class="block text-sm font-medium text-gray-700 mb-1">
                                Promotion Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="promotion_date" id="promotion_date" required
                                value="{{ old('promotion_date', now()->format('Y-m-d')) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('promotion_date') border-red-500 @enderror">
                            <p class="mt-1 text-sm text-gray-500">Completes the current enrollment and starts the new one.</p>
                            @error('promotion_date')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Target Department -->
                        <div>
                            <label for="department_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Target Department <span class="text-red-500">*</span>
                            </label>
                            <select name="department_id" id="department_id" required
                                x-model="departmentId" @change="onDepartmentChange()"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('department_id') border-red-500 @enderror">
                                <option value="">Select Department</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                            @error('department_id')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Target Class, narrowed by department -->
                        <div>
                            <label for="academic_class_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Target Class <span class="text-red-500">*</span>
                            </label>
                            <select name="academic_class_id" id="academic_class_id" required
                                x-model="academicClassId" @change="onClassChange()" :disabled="! departmentId"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 @error('academic_class_id') border-red-500 @enderror">
                                <option value="">Select Class</option>
                                <template x-for="option in classes" :key="option.id">
                                    <option :value="option.id" x-text="option.name"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-sm text-gray-500" x-show="! departmentId" x-cloak>Choose a department first.</p>
                            @error('academic_class_id')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Target Section, narrowed by class. Optional. -->
                        <div>
                            <label for="section_id" class="block text-sm font-medium text-gray-700 mb-1">Target Section</label>
                            <select name="section_id" id="section_id"
                                x-model="sectionId" :disabled="! academicClassId || sections.length === 0"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 @error('section_id') border-red-500 @enderror">
                                <option value="">No section</option>
                                <template x-for="option in sections" :key="option.id">
                                    <option :value="option.id" x-text="option.name"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-sm text-gray-500" x-show="academicClassId && sections.length === 0" x-cloak>
                                This class has no sections. Leave it as "No section".
                            </p>
                            <p class="mt-1 text-sm text-gray-500" x-show="! academicClassId" x-cloak>Optional.</p>
                            @error('section_id')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Notes -->
                        <div class="md:col-span-2">
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea name="notes" id="notes" rows="3" placeholder="Recorded on the new enrollment..."
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('notes') border-red-500 @enderror">{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                        <a href="{{ route('students.show', $student->id) }}"
                           class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </a>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Promote Student
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-layout.admin>
