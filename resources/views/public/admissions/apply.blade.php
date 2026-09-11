<x-guest-layout width="sm:max-w-3xl" title="Online Admission Form">
    <div
        class="py-2"
        x-data="{
            studentType: '{{ old('student_type') }}',
            classesByDepartment: {{ Js::from($classesByDepartment) }},
            // Resolved on the server, one list per student type, already
            // combined and de-duplicated. The browser only picks a list;
            // the student-type mapping and the combining rule are not
            // repeated here, where they could drift from AcademicPlacement.
            termsByStudentType: {{ Js::from($termsByStudentType) }},
            defaultTerms: {{ Js::from($defaultTerms) }},
            terms() {
                return this.termsByStudentType[this.studentType] ?? this.defaultTerms
            },
            departmentsByType: {{ Js::from(\App\Models\AdmissionApplication::STUDENT_TYPE_DEPARTMENTS) }},
            departmentFor(side) { return (this.departmentsByType[this.studentType] ?? {})[side] ?? null },
            classesFor(side) {
                const department = this.departmentFor(side)
                return department ? (this.classesByDepartment[department] ?? []) : []
            },
            onStudentTypeChange() {
                // Drop selections that no longer belong to the chosen programme.
                if (!this.departmentFor('madrassa')) { this.madrassaClassId = '' }
                if (!this.departmentFor('school')) { this.schoolClassId = '' }
            },
            madrassaClassId: '{{ old('madrassa_class_id') }}',
            schoolClassId: '{{ old('school_class_id') }}',
        }"
    >
        <!-- Heading -->
        <div class="mb-6 pb-4 border-b border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800">Online Admission Form</h2>
            <p class="text-sm text-gray-600 mt-1">
                Fill in the student's details below to apply for admission. Fields marked
                <span class="text-red-500">*</span> are required. You will receive an application
                number once your form is submitted.
            </p>
        </div>

        @if($errors->any())
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-sm font-medium text-red-800">
                    Please correct the {{ $errors->count() === 1 ? 'error' : 'errors' }} below and submit the form again.
                </p>
            </div>
        @endif

        @if($academicSession === null)
            {{-- No session is open, so there is nothing to apply for. The form
                 is not rendered at all rather than shown and then rejected on
                 submit: an applicant should not fill in a page of details to
                 be told at the end that it could not be filed. The server
                 refuses the submission as well, in the form request. --}}
            <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
                <p class="text-sm font-medium text-amber-900">Admissions are closed at the moment.</p>
                <p class="text-sm text-amber-800 mt-1">
                    No academic session is currently open for admissions. Please contact the office
                    for the next admission date.
                </p>
            </div>
        @else
        <form method="POST" action="{{ route('public.admissions.store') }}" enctype="multipart/form-data">
            @csrf

            {{-- The year being applied for, shown so the applicant knows and
                 so the printed copy says. Read only, and not a form field:
                 there is no input here, so nothing about the session is
                 posted, and the server resolves it again on submit. --}}
            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-900">
                    <span class="font-medium">Academic Session:</span>
                    <span class="font-semibold">{{ $academicSession->name }}</span>
                </p>
                <p class="text-xs text-blue-800 mt-1">
                    This application will be filed under the academic session shown above.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Student Photo -->
                <div class="md:col-span-2">
                    <label for="photo" class="block text-sm font-medium text-gray-700 mb-1">
                        Student Photo
                    </label>
                    <input
                        type="file"
                        name="photo"
                        id="photo"
                        accept="image/jpeg,image/png"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 @error('photo') border-red-500 @enderror"
                    >
                    <p class="mt-1 text-sm text-gray-500">Optional. JPG, JPEG or PNG, up to 2MB.</p>
                    @error('photo')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Student Name -->
                <div>
                    <label for="student_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Student Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="student_name"
                        id="student_name"
                        value="{{ old('student_name') }}"
                        required
                        autofocus
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('student_name') border-red-500 @enderror"
                    >
                    @error('student_name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Father Name -->
                <div>
                    <label for="father_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Father Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="father_name"
                        id="father_name"
                        value="{{ old('father_name') }}"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('father_name') border-red-500 @enderror"
                    >
                    @error('father_name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Date of Birth -->
                <div>
                    <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">
                        Date of Birth
                    </label>
                    <input
                        type="date"
                        name="date_of_birth"
                        id="date_of_birth"
                        value="{{ old('date_of_birth') }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('date_of_birth') border-red-500 @enderror"
                    >
                    @error('date_of_birth')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Gender -->
                <div>
                    <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">
                        Gender <span class="text-red-500">*</span>
                    </label>
                    <select
                        name="gender"
                        id="gender"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('gender') border-red-500 @enderror"
                    >
                        <option value="">Select Gender</option>
                        @foreach(\App\Models\AdmissionApplication::GENDERS as $gender)
                            <option value="{{ $gender }}" {{ old('gender') === $gender ? 'selected' : '' }}>{{ $gender }}</option>
                        @endforeach
                    </select>
                    @error('gender')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- B-Form Number -->
                <div>
                    <label for="b_form_number" class="block text-sm font-medium text-gray-700 mb-1">
                        B-Form Number
                    </label>
                    <input
                        type="text"
                        name="b_form_number"
                        id="b_form_number"
                        value="{{ old('b_form_number') }}"
                        placeholder="XXXXX-XXXXXXX-X"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('b_form_number') border-red-500 @enderror"
                    >
                    @error('b_form_number')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Student Type -->
                <div>
                    <label for="student_type" class="block text-sm font-medium text-gray-700 mb-1">
                        Student Type <span class="text-red-500">*</span>
                    </label>
                    <select
                        name="student_type"
                        id="student_type"
                        required
                        x-model="studentType"
                        @change="onStudentTypeChange()"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('student_type') border-red-500 @enderror"
                    >
                        <option value="">Select Student Type</option>
                        @foreach(\App\Models\AdmissionApplication::STUDENT_TYPES as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('student_type')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Madrassa / programme class (Hifz or Dars-e-Nizami) -->
                <div x-show="departmentFor('madrassa')" x-cloak>
                    <label for="madrassa_class_id" class="block text-sm font-medium text-gray-700 mb-1">
                        <span x-text="studentType === 'Hifz + School' ? 'Madrassa Class' : 'Class'"></span>
                        <span class="text-red-500">*</span>
                    </label>
                    <select
                        name="madrassa_class_id"
                        id="madrassa_class_id"
                        x-model="madrassaClassId"
                        :required="!! departmentFor('madrassa')"
                        :disabled="! departmentFor('madrassa')"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('madrassa_class_id') border-red-500 @enderror"
                    >
                        <option value="">Select Class</option>
                        <template x-for="option in classesFor('madrassa')" :key="option.id">
                            <option :value="option.id" x-text="option.name"></option>
                        </template>
                    </select>
                    <p class="mt-1 text-sm text-gray-500">
                        Department: <span class="font-medium" x-text="departmentFor('madrassa')"></span>
                    </p>
                    @error('madrassa_class_id')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- School class -->
                <div x-show="departmentFor('school')" x-cloak>
                    <label for="school_class_id" class="block text-sm font-medium text-gray-700 mb-1">
                        School Class <span class="text-red-500">*</span>
                    </label>
                    <select
                        name="school_class_id"
                        id="school_class_id"
                        x-model="schoolClassId"
                        :required="!! departmentFor('school')"
                        :disabled="! departmentFor('school')"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('school_class_id') border-red-500 @enderror"
                    >
                        <option value="">Select School Class</option>
                        <template x-for="option in classesFor('school')" :key="option.id">
                            <option :value="option.id" x-text="option.name"></option>
                        </template>
                    </select>
                    <p class="mt-1 text-sm text-gray-500">
                        Department: <span class="font-medium" x-text="departmentFor('school')"></span>
                    </p>
                    @error('school_class_id')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Father Mobile -->
                <div>
                    <label for="father_mobile" class="block text-sm font-medium text-gray-700 mb-1">
                        Father Mobile <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="father_mobile"
                        id="father_mobile"
                        value="{{ old('father_mobile') }}"
                        placeholder="03XX-XXXXXXX"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('father_mobile') border-red-500 @enderror"
                    >
                    @error('father_mobile')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Mother Mobile -->
                <div>
                    <label for="mother_mobile" class="block text-sm font-medium text-gray-700 mb-1">
                        Mother Mobile
                    </label>
                    <input
                        type="text"
                        name="mother_mobile"
                        id="mother_mobile"
                        value="{{ old('mother_mobile') }}"
                        placeholder="03XX-XXXXXXX"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('mother_mobile') border-red-500 @enderror"
                    >
                    @error('mother_mobile')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Permanent Address -->
                <div class="md:col-span-2">
                    <label for="permanent_address" class="block text-sm font-medium text-gray-700 mb-1">
                        Permanent Address
                    </label>
                    <textarea
                        name="permanent_address"
                        id="permanent_address"
                        rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('permanent_address') border-red-500 @enderror"
                    >{{ old('permanent_address') }}</textarea>
                    @error('permanent_address')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Current Address -->
                <div class="md:col-span-2">
                    <label for="current_address" class="block text-sm font-medium text-gray-700 mb-1">
                        Current Address
                    </label>
                    <textarea
                        name="current_address"
                        id="current_address"
                        rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('current_address') border-red-500 @enderror"
                    >{{ old('current_address') }}</textarea>
                    @error('current_address')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Notes -->
                <div class="md:col-span-2">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                        Notes
                    </label>
                    <textarea
                        name="notes"
                        id="notes"
                        rows="3"
                        placeholder="Anything else you would like us to know..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('notes') border-red-500 @enderror"
                    >{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Important Instructions -->
            <div class="mt-8 pt-6 border-t border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Important Instructions</h3>

                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 max-h-80 overflow-y-auto">
                    {{-- Rendered in the browser rather than by the component,
                         because the applicant chooses their student type on
                         this same page and the instructions follow it. The
                         lists are resolved on the server - one per student
                         type, already combined and de-duplicated - so what
                         changes here is only which of them is shown. Before a
                         type is chosen the institution's defaults stand, so
                         the section is never empty. --}}
                    <div dir="rtl" lang="ur" class="text-right">
                        <h4 class="text-base font-bold text-gray-900 mb-3 leading-loose">
                            {{ \App\Support\StudentTerms::heading() }}
                        </h4>

                        <ul class="space-y-2 list-none">
                            <template x-for="(instruction, index) in terms()" :key="index">
                                <li class="flex items-start text-gray-800 leading-loose">
                                    <span class="ml-2 select-none">&bull;</span>
                                    <span x-text="instruction"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>

                <!-- Agreement -->
                <div class="mt-4">
                    <label for="instructions_accepted" class="flex items-start cursor-pointer">
                        <input
                            type="checkbox"
                            name="instructions_accepted"
                            id="instructions_accepted"
                            value="1"
                            required
                            {{ old('instructions_accepted') ? 'checked' : '' }}
                            class="mt-1 h-4 w-4 flex-shrink-0 rounded border-gray-300 text-blue-600 focus:ring-blue-500 @error('instructions_accepted') border-red-500 @enderror"
                        >
                        <span dir="rtl" lang="ur" class="mr-3 text-gray-800 leading-loose text-right">
                            {{ \App\Support\StudentTerms::agreement() }}
                            <span class="text-red-500">*</span>
                        </span>
                    </label>
                    @error('instructions_accepted')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end space-x-3 mt-6 pt-6 border-t border-gray-200">
                <a
                    href="{{ url('/') }}"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                >
                    Cancel
                </a>
                <button
                    type="submit"
                    class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Submit Application
                </button>
            </div>
        </form>
        @endif
    </div>
</x-guest-layout>
