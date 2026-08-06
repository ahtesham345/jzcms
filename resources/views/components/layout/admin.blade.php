<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ sidebarOpen: false }" x-cloak>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('jzcms.short_name') }} - {{ config('jzcms.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="font-sans antialiased bg-gray-100">
    <div class="min-h-screen">
        <!-- Sidebar -->
        <x-layout.sidebar />

        <!-- Main Content Area -->
        <div class="lg:pl-64">
            <!-- Top Navbar -->
            <x-layout.navbar />

            <!-- Page Content -->
            <main class="py-6">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <!-- Breadcrumb -->
                    @isset($breadcrumbs)
                        <x-layout.breadcrumb :breadcrumbs="$breadcrumbs" />
                    @endisset

                    <!-- Page Header -->
                    @isset($header)
                        <x-layout.page-header>
                            {{ $header }}
                        </x-layout.page-header>
                    @endisset

                    <!-- Flash Messages -->
                    <x-layout.flash-messages />

                    <!-- Main Content -->
                    {{ $slot }}
                </div>
            </main>

            <!-- Footer -->
            <x-layout.footer />
        </div>
    </div>
</body>
</html>
