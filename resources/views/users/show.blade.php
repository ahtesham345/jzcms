<x-layout.admin title="View User">
    <x-slot name="header">
        View User
    </x-slot>

    <div class="max-w-4xl">
        <!-- Back Button -->
        <div class="mb-4">
            <a href="{{ route('users.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Users
            </a>
        </div>

        <!-- Profile Card -->
        <div class="bg-white rounded-lg shadow-sm mb-6">
            <div class="px-6 py-8">
                <div class="flex items-center">
                    <!-- Avatar -->
                    <div class="flex-shrink-0">
                        <div class="h-24 w-24 rounded-full bg-blue-600 flex items-center justify-center">
                            <span class="text-3xl font-bold text-white">
                                {{ strtoupper(substr($user->name, 0, 1)) }}{{ strtoupper(substr(explode(' ', $user->name)[1] ?? '', 0, 1)) }}
                            </span>
                        </div>
                    </div>
                    
                    <!-- User Name and Role -->
                    <div class="ml-6 flex-1">
                        <h2 class="text-2xl font-bold text-gray-900">{{ $user->name }}</h2>
                        <div class="mt-2 flex items-center space-x-3">
                            @if($user->roles->isNotEmpty())
                                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800">
                                    {{ $user->roles->first()->name }}
                                </span>
                            @else
                                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-600">
                                    No Role
                                </span>
                            @endif
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                                Active
                            </span>
                        </div>
                    </div>

                    <!-- Action Button -->
                    @can('users.edit')
                        <div>
                            <a 
                                href="{{ route('users.edit', $user->id) }}" 
                                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                Edit User
                            </a>
                        </div>
                    @endcan
                </div>
            </div>
        </div>

        <!-- Information Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- User Information Card -->
            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">User Information</h3>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <!-- Full Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Full Name</label>
                        <p class="text-base text-gray-900">{{ $user->name }}</p>
                    </div>

                    <!-- Email Address -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Email Address</label>
                        <p class="text-base text-gray-900">{{ $user->email }}</p>
                    </div>

                    <!-- Assigned Role -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Assigned Role</label>
                        @if($user->roles->isNotEmpty())
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800">
                                {{ $user->roles->first()->name }}
                            </span>
                        @else
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-600">
                                No Role
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Account Information Card -->
            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Account Information</h3>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <!-- Account Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Account Status</label>
                        <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                            Active
                        </span>
                    </div>

                    <!-- Created Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Created Date</label>
                        <p class="text-base text-gray-900">{{ $user->created_at->format('F d, Y') }}</p>
                        <p class="text-sm text-gray-500">{{ $user->created_at->diffForHumans() }}</p>
                    </div>

                    <!-- Last Updated Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Last Updated Date</label>
                        <p class="text-base text-gray-900">{{ $user->updated_at->format('F d, Y') }}</p>
                        <p class="text-sm text-gray-500">{{ $user->updated_at->diffForHumans() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-6 flex items-center justify-between">
            <a 
                href="{{ route('users.index') }}" 
                class="inline-flex items-center px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Users
            </a>
            @can('users.edit')
                <a 
                    href="{{ route('users.edit', $user->id) }}" 
                    class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit User
                </a>
            @endcan
        </div>
    </div>
</x-layout.admin>
