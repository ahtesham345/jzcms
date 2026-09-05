<x-layout.admin title="Edit Department">
    <x-slot name="header">
        Edit Department
    </x-slot>

    <div class="max-w-2xl">
        <!-- Back Button -->
        <div class="mb-4">
            <a href="{{ route('departments.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Departments
            </a>
        </div>

        <!-- Form Card -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    Edit Department Information
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    Update the details below to modify the department.
                </p>
            </div>

            <form action="{{ route('departments.update', $department->id) }}" method="POST" class="px-6 py-6 space-y-6">
                @csrf
                @method('PUT')

                <!-- Department Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Department Name <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="name" 
                        id="name" 
                        value="{{ old('name', $department->name) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                        placeholder="e.g., Computer Science"
                        autofocus
                    >
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Department Code -->
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700 mb-2">
                        Department Code <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="code" 
                        id="code" 
                        value="{{ old('code', $department->code) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('code') border-red-500 @enderror"
                        placeholder="e.g., CS"
                        maxlength="20"
                    >
                    @error('code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">
                        Maximum 20 characters. Use a short, unique code.
                    </p>
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
                        maxlength="1000"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('description') border-red-500 @enderror"
                        placeholder="Enter department description..."
                    >{{ old('description', $department->description) }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">
                        Maximum 1000 characters.
                    </p>
                </div>

                <!-- Established Date -->
                <div>
                    <label for="established_date" class="block text-sm font-medium text-gray-700 mb-2">
                        Established Date <span class="text-gray-400">(Optional)</span>
                    </label>
                    <input
                        type="date"
                        name="established_date"
                        id="established_date"
                        value="{{ old('established_date', $department->established_date?->format('Y-m-d')) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('established_date') border-red-500 @enderror"
                    >
                    @error('established_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">
                        The date when this department/program originally started.
                    </p>
                </div>

                <!-- Status Radio Buttons -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Status <span class="text-red-500">*</span>
                    </label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input 
                                type="radio" 
                                name="status" 
                                value="1"
                                {{ old('status', $department->status) == '1' ? 'checked' : '' }}
                                class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-2 focus:ring-blue-500"
                            >
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                        <label class="flex items-center">
                            <input 
                                type="radio" 
                                name="status" 
                                value="0"
                                {{ old('status', $department->status) == '0' ? 'checked' : '' }}
                                class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-2 focus:ring-blue-500"
                            >
                            <span class="ml-2 text-sm text-gray-700">Inactive</span>
                        </label>
                    </div>
                    @error('status')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                    <a 
                        href="{{ route('departments.index') }}" 
                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                        Cancel
                    </a>
                    <button 
                        type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        Update Department
                    </button>
                </div>
            </form>
        </div>

        <!-- Help Text -->
        <div class="mt-4 p-4 bg-blue-50 rounded-lg">
            <div class="flex">
                <svg class="w-5 h-5 text-blue-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="ml-3">
                    <p class="text-sm text-blue-800">
                        <strong>Note:</strong> Fields marked with <span class="text-red-500">*</span> are required. Department code must be unique.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-layout.admin>
