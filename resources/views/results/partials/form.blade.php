@php
    /**
     * The result form, shared by create and edit.
     *
     * $result is null when recording a new one. Everything about the
     * student and the placement is displayed read only: the enrollment is
     * chosen from the list and never edited here, which is what keeps a
     * result tied to the student and track it was opened for.
     *
     * The percentage and the grade are shown, never typed. They are
     * computed by the application from the marks on every save, so what is
     * displayed here is what was stored rather than what was entered.
     */
    $result = $result ?? null;
    $student = $enrollment->student;
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
                <label class="text-sm font-medium text-gray-500">Program</label>
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
            for the <span class="font-medium">{{ $enrollment->academicSession?->name ?? 'current' }}</span> session.
        </p>
    </div>

    <form method="POST"
          action="{{ $result ? route('results.update', $result->id) : route('results.store') }}"
          class="bg-white rounded-lg shadow-sm p-6 space-y-6">
        @csrf
        @if($result)
            @method('PUT')
        @endif

        {{-- Posted so the server can check the enrollment against the student
             it claims to belong to. Neither is trusted: both are re-read from
             the database before anything is written, and on an edit the
             enrollment is taken from the stored row regardless. --}}
        <input type="hidden" name="student_id" value="{{ $student->id }}">
        <input type="hidden" name="student_academic_enrollment_id" value="{{ $enrollment->id }}">

        {{-- Carried so saving returns to the list this student was picked
             from rather than to an unfiltered page. --}}
        @foreach($returnFilters as $key => $value)
            <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">
        @endforeach

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="term" class="block text-sm font-medium text-gray-700 mb-1">
                    Term <span class="text-red-500">*</span>
                </label>
                <select name="term" id="term" required
                        class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    @foreach($terms as $option)
                        <option value="{{ $option }}" {{ old('term', $result?->term ?? $term) === $option ? 'selected' : '' }}>
                            {{ $option }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-sm text-gray-500">One result per term per test for this student.</p>
                <x-input-error :messages="$errors->get('term')" class="mt-2" />
            </div>

            <div>
                <label for="test_type" class="block text-sm font-medium text-gray-700 mb-1">
                    Test Type <span class="text-red-500">*</span>
                </label>
                <select name="test_type" id="test_type" required
                        class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    @foreach($testTypes as $option)
                        <option value="{{ $option }}" {{ old('test_type', $result?->test_type ?? $testTypes[0]) === $option ? 'selected' : '' }}>
                            {{ $option }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('test_type')" class="mt-2" />
            </div>

            <div>
                <label for="total_marks" class="block text-sm font-medium text-gray-700 mb-1">
                    Total Marks <span class="text-red-500">*</span>
                </label>
                <input type="number" name="total_marks" id="total_marks" required step="0.01" min="0.01"
                       value="{{ old('total_marks', $result?->total_marks) }}"
                       placeholder="e.g. 100"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <p class="mt-1 text-sm text-gray-500">Must be greater than zero.</p>
                <x-input-error :messages="$errors->get('total_marks')" class="mt-2" />
            </div>

            <div>
                <label for="obtained_marks" class="block text-sm font-medium text-gray-700 mb-1">
                    Obtained Marks <span class="text-red-500">*</span>
                </label>
                <input type="number" name="obtained_marks" id="obtained_marks" required step="0.01" min="0"
                       value="{{ old('obtained_marks', $result?->obtained_marks) }}"
                       placeholder="e.g. 85"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <p class="mt-1 text-sm text-gray-500">Cannot be negative, and cannot exceed the total marks.</p>
                <x-input-error :messages="$errors->get('obtained_marks')" class="mt-2" />
            </div>

            <div>
                <label for="result_date" class="block text-sm font-medium text-gray-700 mb-1">
                    Result Date <span class="text-red-500">*</span>
                </label>
                <input type="date" name="result_date" id="result_date" required
                       value="{{ old('result_date', $result?->result_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <x-input-error :messages="$errors->get('result_date')" class="mt-2" />
            </div>
        </div>

        {{-- Displayed, never entered. There is no input behind either of
             these: the application computes them from the marks on save, so
             nothing the browser sends can decide what a paper scored. --}}
        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
            <h4 class="text-sm font-semibold text-gray-800">Calculated by the system</h4>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                <div>
                    <label class="text-sm font-medium text-gray-500">Percentage</label>
                    <p class="text-2xl font-bold text-gray-900">
                        {{ $result?->formattedPercentage() ?? 'Calculated on save' }}
                    </p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Grade</label>
                    <p class="text-2xl font-bold text-gray-900">
                        {{ $result?->grade ?? 'Calculated on save' }}
                    </p>
                </div>
            </div>

            <p class="mt-3 text-sm text-gray-600">
                Percentage is obtained marks &divide; total marks &times; 100, rounded to two decimals. The
                grade follows from it:
                <span class="text-gray-700">{{ implode(', ', array_map(fn ($grade, $band) => $grade.' '.$band, array_keys($gradeLegend), $gradeLegend)) }}</span>.
            </p>
        </div>

        <div>
            <label for="remarks" class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
            <textarea name="remarks" id="remarks" rows="3" maxlength="2000"
                      placeholder="e.g. Excellent performance in Tajweed"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('remarks', $result?->remarks) }}</textarea>
            <x-input-error :messages="$errors->get('remarks')" class="mt-2" />
        </div>

        <x-input-error :messages="$errors->get('student_academic_enrollment_id')" />
        <x-input-error :messages="$errors->get('student_id')" />

        <div class="flex items-center justify-end space-x-3 border-t border-gray-200 pt-6">
            <a href="{{ route('results.index', $returnFilters) }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                Cancel
            </a>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                {{ $result ? 'Save Changes' : 'Save Result' }}
            </button>
        </div>
    </form>
</div>
