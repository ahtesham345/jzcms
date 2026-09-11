@props([
    'student' => null,
    'placementByTrack' => null,
    'studentTypes',
    'departmentNamesById',
    'departmentIdsByStudentType',
    'classesByDepartment',
    'sectionsByClass',
    'computerSemesters' => [],
])

@php
    // Where each block starts out. Old input first, so a rejected submission
    // comes back as the admin left it; then the student's current placement
    // when editing; then empty.
    $madrassa = $placementByTrack['Madrassa'] ?? null;
    $school = $placementByTrack['School'] ?? null;
    $computer = $placementByTrack['Computer'] ?? null;

    $initial = [
        'single' => [
            'departmentId' => old('department_id', $student?->department_id),
            'classId' => old('academic_class_id', $student?->academic_class_id),
            'sectionId' => old('section_id', $student?->section_id),
        ],
        'madrassa' => [
            'departmentId' => old('madrassa_department_id', $madrassa?->department_id),
            'classId' => old('madrassa_class_id', $madrassa?->academic_class_id),
            'sectionId' => old('madrassa_section_id', $madrassa?->section_id),
        ],
        'school' => [
            'departmentId' => old('school_department_id', $school?->department_id),
            'classId' => old('school_class_id', $school?->academic_class_id),
            'sectionId' => old('school_section_id', $school?->section_id),
        ],
        'computer' => [
            'departmentId' => old('computer_department_id', $computer?->department_id),
            'classId' => old('computer_class_id', $computer?->academic_class_id),
            'sectionId' => old('computer_section_id', $computer?->section_id),
        ],
    ];

    // The Computer placement's semester, which no other side has. Falls back
    // to the first stage of the course, because that is where a new Computer
    // student starts.
    $semesterId = (string) (
        old(\App\Support\AcademicPlacement::semesterField())
        ?? $computer?->computer_course_semester_id
        ?? \App\Models\ComputerCourse::startingSemester()?->id
        ?? ''
    );

    // Alpine compares against select values, which are strings.
    $initial = collect($initial)
        ->map(fn ($block) => collect($block)->map(fn ($value) => (string) ($value ?? ''))->all())
        ->all();
@endphp

{{--
    Student type -> department -> class -> section, the placement rules
    Admission Management already runs on.

    The department a side may use comes from the same student-type mapping
    the admission form places applicants with, the classes are narrowed to
    that department and the sections to that class. A Hifz + School student
    gets one placement per track, which is what produces its two enrollments.

    All of it is a convenience: the server re-derives every one of these
    relationships from the posted student type and checks it against the
    database, so nothing here decides what is accepted.
--}}
<div
    class="md:col-span-2"
    x-data="{
        studentType: {{ Js::from(old('student_type', $student?->student_type ?? '')) }},
        departmentIdsByStudentType: {{ Js::from($departmentIdsByStudentType) }},
        departmentNamesById: {{ Js::from($departmentNamesById) }},
        classesByDepartment: {{ Js::from($classesByDepartment) }},
        sectionsByClass: {{ Js::from($sectionsByClass) }},
        single: {{ Js::from($initial['single']) }},
        madrassa: {{ Js::from($initial['madrassa']) }},
        school: {{ Js::from($initial['school']) }},
        computer: {{ Js::from($initial['computer']) }},
        computerSemesters: {{ Js::from($computerSemesters) }},
        semesterId: {{ Js::from($semesterId) }},
        allSides: {{ Js::from(array_keys(\App\Support\AcademicPlacement::SIDE_TRACKS)) }},
        semesterSide: {{ Js::from(\App\Support\AcademicPlacement::SEMESTER_SIDE) }},
        sides() {
            return Object.keys(this.departmentIdsByStudentType[this.studentType] ?? {})
        },
        usesSide(side) {
            return this.sides().includes(side)
        },
        usesSemesters() {
            return this.usesSide(this.semesterSide)
        },
        spansBothTracks() {
            return this.sides().length > 1
        },
        soleSide() {
            return this.sides().length === 1 ? this.sides()[0] : null
        },
        departmentIdFor(side) {
            const value = (this.departmentIdsByStudentType[this.studentType] ?? {})[side]
            return value === undefined ? '' : String(value)
        },
        departmentOptionsFor(block) {
            const side = block === 'single' ? this.soleSide() : block
            if (! side) { return [] }
            const id = this.departmentIdFor(side)
            return id === '' ? [] : [id]
        },
        classesFor(block) {
            return this[block].departmentId ? (this.classesByDepartment[this[block].departmentId] ?? []) : []
        },
        sectionsFor(block) {
            return this[block].classId ? (this.sectionsByClass[this[block].classId] ?? []) : []
        },
        setDepartment(block, departmentId) {
            if (this[block].departmentId === departmentId) { return }
            // Changing the department drops the class and section under it.
            this[block].departmentId = departmentId
            this[block].classId = ''
            this[block].sectionId = ''
        },
        onStudentTypeChange() {
            const sole = this.soleSide()
            this.setDepartment('single', sole ? this.departmentIdFor(sole) : '')

            // Every side the mapping knows about, so a type that uses the
            // Computer side fills that block and a type that does not has
            // it cleared rather than left holding a stale placement.
            this.allSides.forEach((side) => {
                this.setDepartment(
                    side,
                    this.spansBothTracks() && this.usesSide(side) ? this.departmentIdFor(side) : ''
                )
            })

            // A student type without a Computer side carries no semester.
            if (! this.usesSemesters()) { this.semesterId = '' }
        },
        onClassChange(block) {
            // Changing the class drops the section under it.
            this[block].sectionId = ''
        },
    }"
    x-init="onStudentTypeChange()"
>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Student Type: decides which placements below apply -->
        <div class="md:col-span-2">
            <label for="student_type" class="block text-sm font-medium text-gray-700 mb-2">
                Student Type <span class="text-red-500">*</span>
            </label>
            <select
                name="student_type"
                id="student_type"
                x-model="studentType"
                @change="onStudentTypeChange()"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('student_type') border-red-500 @enderror"
            >
                <option value="">Select type</option>
                @foreach($studentTypes as $type)
                    <option value="{{ $type }}">{{ $type }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-sm text-gray-500">
                A combined type is placed on both of its programmes, so it asks for both:
                Hifz + School in the madrassa and the school, Dars-e-Nizami + Computer in
                the madrassa and the Computer course.
            </p>
            @error('student_type')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Single-track placement: every student type but Hifz + School. --}}
        <!-- Department -->
        <div x-show="! spansBothTracks()" x-cloak>
            <label for="department_id" class="block text-sm font-medium text-gray-700 mb-2">
                Department <span class="text-red-500">*</span>
            </label>
            <select
                name="department_id"
                id="department_id"
                x-model="single.departmentId"
                @change="single.classId = ''; single.sectionId = ''"
                :disabled="spansBothTracks()"
                :required="! spansBothTracks()"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 @error('department_id') border-red-500 @enderror"
            >
                <option value="">Select department</option>
                <template x-for="id in departmentOptionsFor('single')" :key="id">
                    <option :value="id" x-text="departmentNamesById[id]"></option>
                </template>
            </select>
            <p class="mt-1 text-sm text-gray-500" x-show="! studentType" x-cloak>Choose a student type first.</p>
            @error('department_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Academic Class, narrowed by department -->
        <div x-show="! spansBothTracks()" x-cloak>
            <label for="academic_class_id" class="block text-sm font-medium text-gray-700 mb-2">
                Academic Class <span class="text-red-500">*</span>
            </label>
            <select
                name="academic_class_id"
                id="academic_class_id"
                x-model="single.classId"
                @change="onClassChange('single')"
                :disabled="spansBothTracks() || ! single.departmentId"
                :required="! spansBothTracks()"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 @error('academic_class_id') border-red-500 @enderror"
            >
                <option value="">Select academic class</option>
                <template x-for="option in classesFor('single')" :key="option.id">
                    <option :value="option.id" x-text="option.name"></option>
                </template>
            </select>
            <p class="mt-1 text-sm text-gray-500" x-show="! single.departmentId" x-cloak>Choose a department first.</p>
            @error('academic_class_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Section, narrowed by class -->
        <div x-show="! spansBothTracks()" x-cloak>
            <label for="section_id" class="block text-sm font-medium text-gray-700 mb-2">
                Section
            </label>
            <select
                name="section_id"
                id="section_id"
                x-model="single.sectionId"
                :disabled="spansBothTracks() || ! single.classId || sectionsFor('single').length === 0"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 @error('section_id') border-red-500 @enderror"
            >
                <option value="">No section</option>
                <template x-for="option in sectionsFor('single')" :key="option.id">
                    <option :value="option.id" x-text="option.name"></option>
                </template>
            </select>
            <p class="mt-1 text-sm text-gray-500" x-show="single.classId && sectionsFor('single').length === 0" x-cloak>
                This class has no sections.
            </p>
            @error('section_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- A combined student type: one placement per programme. Each block
             shows only for the student types that actually use that side, so
             a Hifz + School student is asked for a madrassa and a school
             placement and a Dars-e-Nizami + Computer student for a madrassa
             and a Computer one. --}}
        @foreach([
            'madrassa' => 'Madrassa Placement',
            'school' => 'School Placement',
            'computer' => 'Computer Placement',
        ] as $side => $heading)
            <div class="md:col-span-2" x-show="spansBothTracks() && usesSide('{{ $side }}')" x-cloak>
                <h5 class="text-sm font-semibold text-gray-700 mb-3">{{ $heading }}</h5>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Department -->
                    <div>
                        <label for="{{ $side }}_department_id" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ ucfirst($side) }} Department <span class="text-red-500">*</span>
                        </label>
                        <select
                            name="{{ $side }}_department_id"
                            id="{{ $side }}_department_id"
                            x-model="{{ $side }}.departmentId"
                            @change="{{ $side }}.classId = ''; {{ $side }}.sectionId = ''"
                            :disabled="! (spansBothTracks() && usesSide('{{ $side }}'))"
                            :required="spansBothTracks() && usesSide('{{ $side }}')"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 @error($side.'_department_id') border-red-500 @enderror"
                        >
                            <option value="">Select department</option>
                            <template x-for="id in departmentOptionsFor('{{ $side }}')" :key="id">
                                <option :value="id" x-text="departmentNamesById[id]"></option>
                            </template>
                        </select>
                        @error($side.'_department_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Class, narrowed by department -->
                    <div>
                        <label for="{{ $side }}_class_id" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ ucfirst($side) }} Class <span class="text-red-500">*</span>
                        </label>
                        <select
                            name="{{ $side }}_class_id"
                            id="{{ $side }}_class_id"
                            x-model="{{ $side }}.classId"
                            @change="onClassChange('{{ $side }}')"
                            :disabled="! (spansBothTracks() && usesSide('{{ $side }}')) || ! {{ $side }}.departmentId"
                            :required="spansBothTracks() && usesSide('{{ $side }}')"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 @error($side.'_class_id') border-red-500 @enderror"
                        >
                            <option value="">Select class</option>
                            <template x-for="option in classesFor('{{ $side }}')" :key="option.id">
                                <option :value="option.id" x-text="option.name"></option>
                            </template>
                        </select>
                        @error($side.'_class_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Section, narrowed by class -->
                    <div>
                        <label for="{{ $side }}_section_id" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ ucfirst($side) }} Section
                        </label>
                        <select
                            name="{{ $side }}_section_id"
                            id="{{ $side }}_section_id"
                            x-model="{{ $side }}.sectionId"
                            :disabled="! (spansBothTracks() && usesSide('{{ $side }}')) || ! {{ $side }}.classId || sectionsFor('{{ $side }}').length === 0"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 @error($side.'_section_id') border-red-500 @enderror"
                        >
                            <option value="">No section</option>
                            <template x-for="option in sectionsFor('{{ $side }}')" :key="option.id">
                                <option :value="option.id" x-text="option.name"></option>
                            </template>
                        </select>
                        @error($side.'_section_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @if($side === \App\Support\AcademicPlacement::SEMESTER_SIDE)
                        {{-- Where the student is in the Computer course. The
                             Computer programme progresses by semester rather
                             than by class, so this is the field that says
                             where they have got to. It starts on the first
                             semester of the course and the admin may move it
                             - a student transferring in part way through does
                             not begin again at the beginning. --}}
                        <div>
                            <label for="{{ \App\Support\AcademicPlacement::semesterField() }}" class="block text-sm font-medium text-gray-700 mb-2">
                                Computer Semester
                            </label>
                            <select
                                name="{{ \App\Support\AcademicPlacement::semesterField() }}"
                                id="{{ \App\Support\AcademicPlacement::semesterField() }}"
                                x-model="semesterId"
                                :disabled="! (spansBothTracks() && usesSide('{{ $side }}'))"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 @error(\App\Support\AcademicPlacement::semesterField()) border-red-500 @enderror"
                            >
                                <option value="">Select semester</option>
                                <template x-for="option in computerSemesters" :key="option.id">
                                    <option :value="option.id" x-text="option.name"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-sm text-gray-500" x-show="computerSemesters.length === 0" x-cloak>
                                No Computer course has been set up yet.
                            </p>
                            @error(\App\Support\AcademicPlacement::semesterField())
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
