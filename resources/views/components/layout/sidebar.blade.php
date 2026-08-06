<!-- Mobile sidebar overlay -->
<div x-show="sidebarOpen" 
     @click="sidebarOpen = false"
     class="fixed inset-0 z-40 bg-gray-900 bg-opacity-50 lg:hidden"
     x-transition:enter="transition-opacity ease-linear duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-linear duration-300"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
</div>

<!-- Sidebar -->
<div :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
     class="fixed inset-y-0 left-0 z-50 w-64 bg-gray-900 transform transition-transform duration-300 ease-in-out lg:translate-x-0 flex flex-col">
    
    <!-- Sidebar Header -->
    <div class="flex items-center justify-between h-16 px-6 bg-gray-800">
        <a href="{{ route('dashboard') }}" class="flex items-center">
            <span class="text-xl font-bold text-white">{{ config('jzcms.short_name') }}</span>
        </a>
        <button @click="sidebarOpen = false" class="text-gray-400 hover:text-white lg:hidden">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <!-- Sidebar Menu -->
    <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto h-full">
        <!-- Dashboard -->
        <x-layout.sidebar-item 
            href="{{ route('dashboard') }}" 
            :active="request()->routeIs('dashboard')"
            icon="home">
            Dashboard
        </x-layout.sidebar-item>

        <!-- User Management -->
        @can('users.view')
            <x-layout.sidebar-item 
                href="{{ route('users.index') }}" 
                :active="request()->routeIs('users.*')"
                icon="users">
                User Management
            </x-layout.sidebar-item>
        @endcan

        <!-- Student Management -->
        <x-layout.sidebar-item 
            href="#" 
            :active="request()->routeIs('students.*')"
            icon="academic-cap">
            Student Management
        </x-layout.sidebar-item>

        <!-- Teacher Management -->
        <x-layout.sidebar-item 
            href="#" 
            :active="request()->routeIs('teachers.*')"
            icon="user-group">
            Teacher Management
        </x-layout.sidebar-item>

        <!-- Parent Management -->
        <x-layout.sidebar-item 
            href="#" 
            :active="request()->routeIs('parents.*')"
            icon="user-circle">
            Parent Management
        </x-layout.sidebar-item>

        <!-- Attendance -->
        <x-layout.sidebar-item 
            href="#" 
            :active="request()->routeIs('attendance.*')"
            icon="clipboard-document-check">
            Attendance
        </x-layout.sidebar-item>

        <!-- Fee Management -->
        <x-layout.sidebar-item 
            href="#" 
            :active="request()->routeIs('fees.*')"
            icon="currency-dollar">
            Fee Management
        </x-layout.sidebar-item>

        <!-- Academic -->
        <x-layout.sidebar-item 
            href="#" 
            :active="request()->routeIs('academic.*')"
            icon="book-open">
            Academic
        </x-layout.sidebar-item>

        <!-- Hifz & Quran -->
        <x-layout.sidebar-item 
            href="#" 
            :active="request()->routeIs('hifz.*')"
            icon="sparkles">
            Hifz & Quran
        </x-layout.sidebar-item>

        <!-- Discipline -->
        <x-layout.sidebar-item 
            href="#" 
            :active="request()->routeIs('discipline.*')"
            icon="shield-check">
            Discipline
        </x-layout.sidebar-item>

        <!-- Reports -->
        <x-layout.sidebar-item 
            href="#" 
            :active="request()->routeIs('reports.*')"
            icon="document-chart-bar">
            Reports
        </x-layout.sidebar-item>

        <!-- Settings -->
        <x-layout.sidebar-item 
            href="#" 
            :active="request()->routeIs('settings.*')"
            icon="cog">
            Settings
        </x-layout.sidebar-item>
    </nav>

    <!-- Sidebar Footer -->
    <div class="px-4 py-3 border-t border-gray-800">
        <p class="text-xs text-gray-400 text-center">v1.0</p>
    </div>
</div>
