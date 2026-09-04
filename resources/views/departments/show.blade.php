<x-layout.admin title="View Department">
    <x-slot name="header">
        View Department
    </x-slot>

    <div class="max-w-4xl">
        <!-- Back Button -->
        <div class="mb-4">
            <a href="{{ route('departments.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Departments
            </a>
        </div>

        <!-- Department Header Card -->
        <div class="bg-white rounded-lg shadow-sm mb-6">
            <div class="px-6 py-8">
                <div class="flex items-center justify-between">
                    <!-- Department Name and Badges -->
                    <div class="flex-1">
                        <h2 class="text-2xl font-bold text-gray-900">{{ $department->name }}</h2>
                        <div class="mt-3 flex items-center space-x-3">
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded bg-gray-100 text-gray-800">
                                {{ $department->code }}
                            </span>
                            @if($department->status)
                                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                                    Active
                                </span>
                            @else
                                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-600">
                                    Inactive
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div>
                        <a 
                            href="{{ route('departments.edit', $department->id) }}" 
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit Department
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Information Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Department Details Card -->
            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Department Details</h3>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <!-- Department Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Department Name</label>
                        <p class="text-base text-gray-900">{{ $department->name }}</p>
                    </div>

                    <!-- Department Code -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Department Code</label>
                        <span class="inline-flex px-3 py-1 text-sm font-semibold rounded bg-gray-100 text-gray-800">
                            {{ $department->code }}
                        </span>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Description</label>
                        <p class="text-base text-gray-900">
                            {{ $department->description ?? 'No description provided.' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Status Information Card -->
            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Status Information</h3>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Status</label>
                        @if($department->status)
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                                Active
                            </span>
                        @else
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-600">
                                Inactive
                            </span>
                        @endif
                    </div>

                    <!-- Created Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Created Date</label>
                        <p class="text-base text-gray-900">{{ $department->created_at->format('F d, Y') }}</p>
                        <p class="text-sm text-gray-500">{{ $department->created_at->diffForHumans() }}</p>
                    </div>

                    <!-- Last Updated Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Last Updated Date</label>
                        <p class="text-base text-gray-900">{{ $department->updated_at->format('F d, Y') }}</p>
                        <p class="text-sm text-gray-500">{{ $department->updated_at->diffForHumans() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-6 flex items-center justify-between">
            <a 
                href="{{ route('departments.index') }}" 
                class="inline-flex items-center px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Departments
            </a>
            <a 
                href="{{ route('departments.edit', $department->id) }}" 
                class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit Department
            </a>
        </div>
    </div>
</x-layout.admin>
