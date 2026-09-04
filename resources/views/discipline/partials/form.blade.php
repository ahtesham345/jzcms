@php
    /**
     * The discipline record form, shared by create and edit.
     *
     * $record is null when recording a new incident.
     *
     * There is no recorder field and no hidden input for one. The recorder
     * is the authenticated user and the controller sets it from the
     * session, so nothing this form sends can decide who is credited with
     * an entry. On an edit it is displayed read only, as the fact it is.
     *
     * There is no enrollment, class or session field either. A discipline
     * record belongs to the student, which is what keeps it on file after
     * the student is promoted.
     */
    $record = $record ?? null;
    $selectedStudentId = old('student_id', $selectedStudentId);
@endphp

<form method="POST"
      action="{{ $record ? route('discipline.update', $record->id) : route('discipline.store') }}"
      class="bg-white rounded-lg shadow-sm p-6 space-y-6">
    @csrf
    @if($record)
        @method('PUT')
    @endif

    {{-- Carried so saving returns to the list this record was opened from
         rather than to an unfiltered page. Whitelisted server side before
         it reaches the redirect. --}}
    @foreach($returnFilters as $key => $value)
        <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">
    @endforeach

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="md:col-span-2">
            <label for="student_id" class="block text-sm font-medium text-gray-700 mb-1">
                Student <span class="text-red-500">*</span>
            </label>
            {{-- The list narrows what an administrator sees; it does not
                 decide what is accepted. The id is checked against the
                 students table on save. --}}
            <select name="student_id" id="student_id" required
                    class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('student_id') border-red-500 @enderror">
                <option value="">Select Student</option>
                @foreach($students as $option)
                    <option value="{{ $option->id }}" {{ (int) $selectedStudentId === $option->id ? 'selected' : '' }}>
                        {{ $option->registration_number }} — {{ $option->full_name }}@if($option->roll_number) (Roll No. {{ $option->roll_number }})@endif
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-sm text-gray-500">
                The incident belongs to the student and stays on their record after a promotion or a change of class.
            </p>
            <x-input-error :messages="$errors->get('student_id')" class="mt-2" />
        </div>

        <div>
            <label for="date" class="block text-sm font-medium text-gray-700 mb-1">
                Date <span class="text-red-500">*</span>
            </label>
            <input type="date" name="date" id="date" required
                   value="{{ old('date', $record?->date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <x-input-error :messages="$errors->get('date')" class="mt-2" />
        </div>

        <div>
            <label for="category" class="block text-sm font-medium text-gray-700 mb-1">
                Category <span class="text-red-500">*</span>
            </label>
            <select name="category" id="category" required
                    class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Category</option>
                @foreach($categories as $option)
                    <option value="{{ $option }}" {{ old('category', $record?->category) === $option ? 'selected' : '' }}>
                        {{ $option }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('category')" class="mt-2" />
        </div>

        <div>
            <label for="severity" class="block text-sm font-medium text-gray-700 mb-1">
                Severity <span class="text-red-500">*</span>
            </label>
            <select name="severity" id="severity" required
                    class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Severity</option>
                @foreach($severities as $option)
                    <option value="{{ $option }}" {{ old('severity', $record?->severity) === $option ? 'selected' : '' }}>
                        {{ $option }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-sm text-gray-500">A High severity incident marks the student's profile as a Serious Concern.</p>
            <x-input-error :messages="$errors->get('severity')" class="mt-2" />
        </div>

        <div>
            <label for="action_taken" class="block text-sm font-medium text-gray-700 mb-1">Action Taken</label>
            {{-- Free text on purpose: the office writes what it actually
                 did. The suggestions below are a convenience, not a
                 list the server enforces. --}}
            <input type="text" name="action_taken" id="action_taken" maxlength="255"
                   list="discipline-actions"
                   value="{{ old('action_taken', $record?->action_taken) }}"
                   placeholder="e.g. Verbal Warning"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <datalist id="discipline-actions">
                <option value="Verbal Warning"></option>
                <option value="Written Warning"></option>
                <option value="Counseling"></option>
                <option value="Parent Contact"></option>
                <option value="Other"></option>
            </datalist>
            <p class="mt-1 text-sm text-gray-500">Optional. Write what was done, in the office's own words.</p>
            <x-input-error :messages="$errors->get('action_taken')" class="mt-2" />
        </div>
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
            Description <span class="text-red-500">*</span>
        </label>
        <textarea name="description" id="description" rows="4" required maxlength="2000"
                  placeholder="What happened?"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('description', $record?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <div>
        <label for="remarks" class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
        <textarea name="remarks" id="remarks" rows="3" maxlength="2000"
                  placeholder="Anything the office should note alongside the incident"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('remarks', $record?->remarks) }}</textarea>
        <x-input-error :messages="$errors->get('remarks')" class="mt-2" />
    </div>

    {{-- Displayed, never entered. There is no input behind this: the
         controller stamps the authenticated user on save, and an edit
         leaves it as it was. --}}
    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
        <h4 class="text-sm font-semibold text-gray-800">Recorded By</h4>
        <p class="mt-2 text-gray-900">
            {{ $record ? ($record->recorder?->name ?? 'Not recorded') : auth()->user()->name }}
        </p>
        <p class="mt-2 text-sm text-gray-600">
            @if($record)
                The user who first entered this record. Correcting it does not change who recorded it.
            @else
                Taken from the signed-in user when the record is saved.
            @endif
        </p>
    </div>

    <div class="flex items-center justify-end space-x-3 border-t border-gray-200 pt-6">
        <a href="{{ route('discipline.index', $returnFilters) }}"
           class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
            Cancel
        </a>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            {{ $record ? 'Save Changes' : 'Save Record' }}
        </button>
    </div>
</form>
