<x-layout.admin title="View Academic Session">
    <x-slot name="header">
        View Academic Session
    </x-slot>

    <div class="max-w-4xl">
        <!-- Back Button -->
        <div class="mb-4">
            <a href="{{ route('academic-sessions.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Academic Sessions
            </a>
        </div>

        <!-- Session Header Card -->
        <div class="bg-white rounded-lg shadow-sm mb-6">
            <div class="px-6 py-8">
                <div class="flex items-center justify-between">
                    <!-- Session Name and Badges -->
                    <div class="flex-1">
                        <h2 class="text-2xl font-bold text-gray-900">{{ $session->name }}</h2>
                        <div class="mt-3 flex items-center space-x-3">
                            @if($session->is_current)
                                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                                    Current Session
                                </span>
                            @endif
                            @if($session->status)
                                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800">
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
                            href="{{ route('academic-sessions.edit', $session->id) }}" 
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit Session
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Information Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Session Details Card -->
            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Session Details</h3>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <!-- Session Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Session Name</label>
                        <p class="text-base text-gray-900">{{ $session->name }}</p>
                    </div>

                    <!-- Start Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Start Date</label>
                        <p class="text-base text-gray-900">{{ \Carbon\Carbon::parse($session->start_date)->format('F d, Y') }}</p>
                    </div>

                    <!-- End Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">End Date</label>
                        <p class="text-base text-gray-900">{{ \Carbon\Carbon::parse($session->end_date)->format('F d, Y') }}</p>
                    </div>

                    <!-- Duration -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Duration</label>
                        <p class="text-base text-gray-900">
                            {{ \Carbon\Carbon::parse($session->start_date)->diffInDays(\Carbon\Carbon::parse($session->end_date)) }} days
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
                    <!-- Current Session Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Current Session</label>
                        @if($session->is_current)
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                                Yes
                            </span>
                        @else
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-600">
                                No
                            </span>
                        @endif
                    </div>

                    <!-- Active Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Status</label>
                        @if($session->status)
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800">
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
                        <p class="text-base text-gray-900">{{ $session->created_at->format('F d, Y') }}</p>
                        <p class="text-sm text-gray-500">{{ $session->created_at->diffForHumans() }}</p>
                    </div>

                    <!-- Last Updated Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Last Updated Date</label>
                        <p class="text-base text-gray-900">{{ $session->updated_at->format('F d, Y') }}</p>
                        <p class="text-sm text-gray-500">{{ $session->updated_at->diffForHumans() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-6 flex items-center justify-between">
            <a 
                href="{{ route('academic-sessions.index') }}" 
                class="inline-flex items-center px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Academic Sessions
            </a>
            <a 
                href="{{ route('academic-sessions.edit', $session->id) }}" 
                class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit Session
            </a>
        </div>
    </div>
</x-layout.admin>
