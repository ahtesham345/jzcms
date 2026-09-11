<x-layout.admin title="Edit Computer Course">
    <x-slot name="header">
        Edit Computer Course
    </x-slot>

    <div class="max-w-2xl">
        <!-- Back Button -->
        <div class="mb-4">
            <a href="{{ route('computer-course.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Computer Course
            </a>
        </div>

        <!-- Form Card -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    Course Details
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    The Computer department's course, as it is described to students.
                </p>
            </div>

            <form action="{{ route('computer-course.update', $course->id) }}" method="POST" class="px-6 py-6 space-y-6">
                @csrf
                @method('PUT')

                <!-- Course Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Course Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        id="name"
                        value="{{ old('name', $course->name) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                        autofocus
                    >
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Duration -->
                    <div>
                        <label for="duration_years" class="block text-sm font-medium text-gray-700 mb-2">
                            Duration in Years <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            name="duration_years"
                            id="duration_years"
                            min="1"
                            max="10"
                            value="{{ old('duration_years', $course->duration_years) }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('duration_years') border-red-500 @enderror"
                        >
                        @error('duration_years')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Semester Count -->
                    <div>
                        <label for="semester_count" class="block text-sm font-medium text-gray-700 mb-2">
                            Number of Semesters <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            name="semester_count"
                            id="semester_count"
                            min="1"
                            max="20"
                            value="{{ old('semester_count', $course->semester_count) }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('semester_count') border-red-500 @enderror"
                        >
                        @error('semester_count')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">
                            Describes the course. Changing it does not add or remove semester records.
                        </p>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description <span class="text-gray-400">(Optional)</span>
                    </label>
                    <textarea
                        name="description"
                        id="description"
                        rows="4"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('description') border-red-500 @enderror"
                    >{{ old('description', $course->description) }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                        Status
                    </label>
                    <select
                        name="status"
                        id="status"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="1" {{ old('status', $course->status) ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ old('status', $course->status) ? '' : 'selected' }}>Inactive</option>
                    </select>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                    <a href="{{ route('computer-course.index') }}"
                       class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                        Save Course
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layout.admin>
