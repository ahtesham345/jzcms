@props([
    'action',
    'method' => 'POST',
    'enrollment' => null,
    'academicSessions',
    'departments',
    'classesByDepartment',
    'sectionsByClass',
    'academicTracks',
    'enrollmentStatuses',
    'cancelUrl',
    'submitLabel' => 'Save Enrollment',
])

{{--
    Department -> Class -> Section dependent selects, following the same
    Alpine pattern as the public admission form: the whole map is handed to
    the browser once and narrowed client side, with the server re-checking
    every relationship regardless.
--}}
<form
    method="POST"
    action="{{ $action }}"
    class="space-y-4"
    x-data="{
        departmentId: '{{ old('department_id', $enrollment?->department_id) }}',
        academicClassId: '{{ old('academic_class_id', $enrollment?->academic_class_id) }}',
        sectionId: '{{ old('section_id', $enrollment?->section_id) }}',
        classesByDepartment: {{ Js::from($classesByDepartment) }},
        sectionsByClass: {{ Js::from($sectionsByClass) }},
        get classes() {
            return this.departmentId ? (this.classesByDepartment[this.departmentId] ?? []) : []
        },
        get sections() {
            return this.academicClassId ? (this.sectionsByClass[this.academicClassId] ?? []) : []
        },
        onDepartmentChange() {
            // Drop selections that no longer belong to the chosen department.
            this.academicClassId = ''
            this.sectionId = ''
        },
        onClassChange() {
            this.sectionId = ''
        },
    }"
>
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Academic Session -->
        <div>
            <label for="academic_session_id" class="block text-sm font-medium text-gray-700 mb-1">
                Academic Session <span class="text-red-500">*</span>
            </label>
            <select name="academic_session_id" id="academic_session_id" required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('academic_session_id') border-red-500 @enderror">
                <option value="">Select Session</option>
                @foreach($academicSessions as $session)
                    <option value="{{ $session->id }}" {{ (int) old('academic_session_id', $enrollment?->academic_session_id) === $session->id ? 'selected' : '' }}>
                        {{ $session->name }}
                    </option>
                @endforeach
            </select>
            @error('academic_session_id')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Track -->
        <div>
            <label for="academic_track" class="block text-sm font-medium text-gray-700 mb-1">
                Track <span class="text-red-500">*</span>
            </label>
            <select name="academic_track" id="academic_track" required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('academic_track') border-red-500 @enderror">
                <option value="">Select Track</option>
                @foreach($academicTracks as $track)
                    <option value="{{ $track }}" {{ old('academic_track', $enrollment?->academic_track) === $track ? 'selected' : '' }}>{{ $track }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-sm text-gray-500">A student may hold one active enrollment per track.</p>
            @error('academic_track')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Department -->
        <div>
            <label for="department_id" class="block text-sm font-medium text-gray-700 mb-1">
                Department <span class="text-red-500">*</span>
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

        <!-- Class, narrowed by department -->
        <div>
            <label for="academic_class_id" class="block text-sm font-medium text-gray-700 mb-1">
                Class <span class="text-red-500">*</span>
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

        <!-- Section, narrowed by class. Optional throughout. -->
        <div>
            <label for="section_id" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
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

        <!-- Status -->
        <div>
            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                Status <span class="text-red-500">*</span>
            </label>
            <select name="status" id="status" required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('status') border-red-500 @enderror">
                @foreach($enrollmentStatuses as $statusOption)
                    <option value="{{ $statusOption }}" {{ old('status', $enrollment?->status ?? 'Active') === $statusOption ? 'selected' : '' }}>{{ $statusOption }}</option>
                @endforeach
            </select>
            @error('status')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Enrollment Date -->
        <div>
            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">
                Enrollment Date <span class="text-red-500">*</span>
            </label>
            <input type="date" name="start_date" id="start_date" required
                value="{{ old('start_date', $enrollment?->start_date?->format('Y-m-d')) }}"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('start_date') border-red-500 @enderror">
            @error('start_date')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Completion Date -->
        <div>
            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">Completion Date</label>
            <input type="date" name="end_date" id="end_date"
                value="{{ old('end_date', $enrollment?->end_date?->format('Y-m-d')) }}"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('end_date') border-red-500 @enderror">
            <p class="mt-1 text-sm text-gray-500">Optional. Set when the enrollment is completed or withdrawn.</p>
            @error('end_date')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Notes -->
        <div class="md:col-span-2">
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea name="notes" id="notes" rows="3"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('notes') border-red-500 @enderror">{{ old('notes', $enrollment?->notes) }}</textarea>
            @error('notes')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="flex items-center justify-end space-x-2">
        <a href="{{ $cancelUrl }}" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-white transition-colors">
            Cancel
        </a>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            {{ $submitLabel }}
        </button>
    </div>
</form>
