@php
    /**
     * The daily record form, shared by create and edit.
     *
     * $record is null when creating. Everything about the student and the
     * placement is displayed read only: the enrollment is chosen from the
     * roster and never edited here, which is what keeps a record tied to
     * the student and track it was opened for.
     */
    $record = $record ?? null;
    $student = $enrollment->student;

    // Quantities are free text on purpose: a madrassa records "1 page",
    // "half page" or "1/2 para", and this project has no page/para
    // arithmetic to turn those into numbers.
    $placeholders = [
        'sabaq' => 'e.g. Surah Al-Baqarah, from ayah 1',
        'sabaq_quantity' => 'e.g. 1 page, half page, 1/2 para',
        'sabqi' => 'e.g. Para 3 revision',
        'sabqi_quantity' => 'e.g. 3 pages',
        'manzil' => 'e.g. Para 1 to 5',
        'manzil_quantity' => 'e.g. 1 para',
        'next_sabaq' => 'e.g. Surah Al-Baqarah, ayah 20 onwards',
        'subject_book' => 'e.g. Nahw Mir',
        'todays_lesson' => 'e.g. Lesson 12',
        'lesson_topic_covered' => 'e.g. Murakkab Naqis',
        'revision' => 'e.g. Lessons 9 to 11',
        'next_lesson' => 'e.g. Lesson 13',
    ];
@endphp

<div class="space-y-6">
    <!-- Student and placement, read only -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Student</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="text-sm font-medium text-gray-500">Student</label>
                <p class="text-gray-900">
                    <a href="{{ route('students.show', $student->id) }}" class="text-blue-600 hover:text-blue-800">
                        {{ $student->full_name }}
                    </a>
                </p>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-500">Registration No.</label>
                <p class="text-gray-900">{{ $student->registration_number }}</p>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-500">Roll No.</label>
                <p class="text-gray-900">{{ $student->roll_number ?: 'Not assigned' }}</p>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-500">Programme</label>
                <p class="text-gray-900">{{ $student->student_type }}</p>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-500">Academic Session</label>
                <p class="text-gray-900">{{ $enrollment->academicSession?->name ?? 'N/A' }}</p>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-500">Department</label>
                <p class="text-gray-900">{{ $enrollment->department?->name ?? 'N/A' }}</p>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-500">Class</label>
                <p class="text-gray-900">{{ $enrollment->academicClass?->name ?? 'N/A' }}</p>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-500">Section</label>
                <p class="text-gray-900">{{ $enrollment->section?->name ?? 'No section' }}</p>
            </div>
        </div>

        {{-- The Madrassa track, spelled out. A Hifz + School student also
             holds a school enrollment, and this page never touches it. --}}
        <p class="mt-4 text-sm text-gray-600">
            Recorded against this student's <span class="font-medium">{{ $enrollment->academic_track }}</span> enrollment
            @if($enrollment->status !== 'Active')
                <span class="text-gray-500">({{ $enrollment->status }} placement, kept for historical accuracy)</span>
            @endif
            as a <span class="font-medium">{{ $recordType }}</span> daily record.
        </p>
    </div>

    <form method="POST"
          action="{{ $record ? route('hifz.update', $record->id) : route('hifz.store') }}"
          class="bg-white rounded-lg shadow-sm p-6 space-y-6">
        @csrf
        @if($record)
            @method('PUT')
        @endif

        {{-- Posted so the server can check the enrollment against the student
             it claims to belong to. Neither is trusted: both are re-read from
             the database before anything is written. --}}
        <input type="hidden" name="student_id" value="{{ $student->id }}">
        <input type="hidden" name="student_academic_enrollment_id" value="{{ $enrollment->id }}">

        {{-- Carried so saving returns to the roster this student was picked
             from rather than to an unfiltered page. --}}
        @foreach($returnFilters as $key => $value)
            <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">
        @endforeach

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="record_date" class="block text-sm font-medium text-gray-700 mb-1">
                    Date <span class="text-red-500">*</span>
                </label>
                <input type="date" name="record_date" id="record_date" required
                       value="{{ old('record_date', $record?->record_date?->format('Y-m-d') ?? $recordDate) }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <p class="mt-1 text-sm text-gray-500">Saturday and Sunday are off days and cannot be recorded.</p>
                <x-input-error :messages="$errors->get('record_date')" class="mt-2" />
            </div>

            <div>
                <label for="teacher_id" class="block text-sm font-medium text-gray-700 mb-1">Teacher</label>
                <select name="teacher_id" id="teacher_id"
                        class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Not recorded</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" {{ (int) old('teacher_id', $record?->teacher_id) === $teacher->id ? 'selected' : '' }}>
                            {{ $teacher->full_name }}{{ $teacher->teacher_id ? ' (' . $teacher->teacher_id . ')' : '' }}
                        </option>
                    @endforeach
                </select>
                {{-- Optional for this chunk: the project records teachers and
                     which classes they teach, but nothing that says who took a
                     given lesson on a given day. --}}
                <p class="mt-1 text-sm text-gray-500">Optional. Chosen from the existing teacher records.</p>
                <x-input-error :messages="$errors->get('teacher_id')" class="mt-2" />
            </div>
        </div>

        <div>
            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">
                {{ $recordType }} — Today's Work
            </h3>

            {{-- Only this record type's fields are rendered, and only these
                 are accepted on save. A Dars-e-Nizami student is never asked
                 for a Sabaq. --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($workFields as $field)
                    <div>
                        <label for="{{ $field }}" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ $fieldLabels[$field] ?? $field }}
                        </label>
                        <input type="text" name="{{ $field }}" id="{{ $field }}" maxlength="255"
                               value="{{ old($field, $record?->{$field}) }}"
                               placeholder="{{ $placeholders[$field] ?? '' }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <x-input-error :messages="$errors->get($field)" class="mt-2" />
                    </div>
                @endforeach
            </div>

            <p class="mt-4 text-sm text-gray-500">
                Quantities are recorded as written: "1 page", "half page", "1/2 para", "1 para".
                At least one part of the day must be filled in.
            </p>
        </div>

        <div>
            <label for="remarks" class="block text-sm font-medium text-gray-700 mb-1">Teacher Remarks</label>
            <textarea name="remarks" id="remarks" rows="3" maxlength="2000"
                      placeholder="e.g. Good performance"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('remarks', $record?->remarks) }}</textarea>
            <x-input-error :messages="$errors->get('remarks')" class="mt-2" />
        </div>

        <x-input-error :messages="$errors->get('student_academic_enrollment_id')" />
        <x-input-error :messages="$errors->get('student_id')" />

        <div class="flex items-center justify-end space-x-3 border-t border-gray-200 pt-6">
            <a href="{{ route('hifz.index', $returnFilters) }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                Cancel
            </a>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                {{ $record ? 'Save Changes' : 'Save Daily Record' }}
            </button>
        </div>
    </form>
</div>
