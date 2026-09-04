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
    @php
        // Resolved once for this component. Setting::current() is memoised
        // on the container, so the navbar, the page title and the footer
        // asking for it too costs no further query.
        $institution = \App\Models\Setting::current();
    @endphp
    <div class="flex items-center justify-between h-16 px-6 bg-gray-800">
        {{-- The logo when one has been uploaded and the file is still
             there, the institution's name when not, and the project's own
             name behind that. hasLogo() checks the file, so a path whose
             file has gone falls through to the name rather than drawing a
             broken image. --}}
        {{-- Centred only when there is a logo. The name fallback keeps the
             left alignment it already had. --}}
        <a href="{{ route('dashboard') }}"
           class="flex items-center min-w-0 flex-1 mr-2 {{ $institution->hasLogo() ? 'justify-center' : '' }}">
            @if($institution->hasLogo())
                {{-- Bounded by max-height and max-width rather than given a
                     fixed size, with object-contain, so a large logo is
                     scaled down into the existing 16-unit header and a
                     small one is left alone - and neither is stretched.
                     The name follows as the alt text, which is what a
                     screen reader and a failed image both get. --}}
                <img src="{{ $institution->logoUrl() }}"
                     alt="{{ $institution->brandName() }}"
                     class="max-h-10 max-w-full w-auto object-contain">
            @else
                <span class="text-base font-bold text-white leading-tight truncate">
                    {{ $institution->brandName() }}
                </span>
            @endif
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

        <!-- Admission Management -->
        <x-layout.sidebar-item
            href="{{ route('admissions.index') }}"
            :active="request()->routeIs('admissions.*')"
            icon="clipboard-document-list">
            Admission Management
        </x-layout.sidebar-item>

        <!-- Student Management -->
        <x-layout.sidebar-item 
            href="{{ route('students.index') }}" 
            :active="request()->routeIs('students.*')"
            icon="academic-cap">
            Student Management
        </x-layout.sidebar-item>

        <!-- Teacher Management -->
        <x-layout.sidebar-item
            href="{{ route('teachers.index') }}"
            :active="request()->routeIs('teachers.*')"
            icon="user-group">
            Teacher Management
        </x-layout.sidebar-item>

        <!-- Parent Management -->
        <x-layout.sidebar-item
            href="{{ route('parents.index') }}"
            :active="request()->routeIs('parents.*')"
            icon="user-circle">
            Parent Management
        </x-layout.sidebar-item>

        <!-- Attendance -->
        <x-layout.sidebar-item
            href="{{ route('attendance.index') }}"
            :active="request()->routeIs('attendance.*')"
            icon="clipboard-document-check">
            Attendance
        </x-layout.sidebar-item>

        <!-- Prayer Attendance -->
        {{-- Its own item, next to Attendance rather than inside it: the two
             are different registers. Attendance is academic; this is the
             five daily prayers, and only for Madrassa students. --}}
        <x-layout.sidebar-item
            href="{{ route('prayer-attendance.index') }}"
            :active="request()->routeIs('prayer-attendance.*')"
            icon="moon">
            Prayer Attendance
        </x-layout.sidebar-item>

        <!-- Fee Management -->
        <x-layout.sidebar-item 
            href="#" 
            :active="request()->routeIs('fees.*')"
            icon="currency-dollar">
            Fee Management
        </x-layout.sidebar-item>

        <!-- Academic Management -->
        <x-layout.sidebar-item
            href="{{ route('academics.index') }}"
            :active="request()->routeIs('academics.*')"
            icon="book-open">
            Academic Management
        </x-layout.sidebar-item>

        <!-- Hifz & Quran -->
        <x-layout.sidebar-item
            href="{{ route('hifz.index') }}"
            :active="request()->routeIs('hifz.*')"
            icon="sparkles">
            Hifz & Quran
        </x-layout.sidebar-item>

        <!-- Results -->
        {{-- Its own top-level item, next to Hifz & Quran: a Grand Test
             result is a different record from a day's work, and only
             Madrassa students have one. --}}
        <x-layout.sidebar-item
            href="{{ route('results.index') }}"
            :active="request()->routeIs('results.*')"
            icon="trophy">
            Results
        </x-layout.sidebar-item>

        <!-- Discipline -->
        {{-- Its own top-level item, next to the other student-facing
             registers: an incident belongs to the student rather than to a
             class or a term, and every programme has one. --}}
        <x-layout.sidebar-item
            href="{{ route('discipline.index') }}"
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

        <!-- Master Data (Collapsible) -->
        <div x-data="{ open: {{ request()->routeIs('academic-sessions.*') || request()->routeIs('departments.*') || request()->routeIs('classes.*') || request()->routeIs('sections.*') ? 'true' : 'false' }} }">
            <button 
                @click="open = !open"
                class="flex items-center justify-between w-full px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-150 text-gray-300 hover:bg-gray-800 hover:text-white"
            >
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <span>Master Data</span>
                </div>
                <svg 
                    class="w-4 h-4 transition-transform duration-200"
                    :class="open ? 'transform rotate-180' : ''"
                    fill="none" 
                    stroke="currentColor" 
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            
            <div 
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-2"
                class="ml-4 mt-1 space-y-1"
            >
                <x-layout.sidebar-item 
                    href="{{ route('academic-sessions.index') }}" 
                    :active="request()->routeIs('academic-sessions.*')"
                    icon="calendar">
                    Academic Sessions
                </x-layout.sidebar-item>

                <x-layout.sidebar-item 
                    href="{{ route('departments.index') }}" 
                    :active="request()->routeIs('departments.*')"
                    icon="building-office">
                    Departments
                </x-layout.sidebar-item>

                <x-layout.sidebar-item 
                    href="{{ route('classes.index') }}" 
                    :active="request()->routeIs('classes.*')"
                    icon="academic-cap">
                    Classes
                </x-layout.sidebar-item>

                <x-layout.sidebar-item 
                    href="{{ route('sections.index') }}" 
                    :active="request()->routeIs('sections.*')"
                    icon="rectangle-group">
                    Sections
                </x-layout.sidebar-item>
            </div>
        </div>

        <!-- Settings -->
        {{-- The existing item, pointed at the page it was always a
             placeholder for. Behind the same permission check as User
             Management, so it is not offered to somebody the route would
             then refuse. --}}
        @can('settings.view')
            <x-layout.sidebar-item
                href="{{ route('settings.edit') }}"
                :active="request()->routeIs('settings.*')"
                icon="cog">
                Settings
            </x-layout.sidebar-item>
        @endcan
    </nav>

    <!-- Sidebar Footer -->
    <div class="px-4 py-3 border-t border-gray-800">
        <p class="text-xs text-gray-400 text-center">v1.0</p>
    </div>
</div>
