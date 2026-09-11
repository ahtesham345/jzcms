<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ \App\Models\Setting::current()->brandName() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-50">
        <!-- Header with Auth Links -->
        @if (Route::has('login'))
            <div class="p-6 text-right">
                @auth
                    <a href="{{ url('/dashboard') }}" class="text-sm text-gray-700 underline">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="text-sm text-gray-700 underline">Log in</a>
                @endauth
            </div>
        @endif

        <!-- Main Content -->
        <div class="flex flex-col items-center justify-center min-h-[calc(100vh-100px)]">
            <div class="text-center">
                @php($institution = \App\Models\Setting::current())
                @if($institution->hasLogo())
                    <img src="{{ $institution->logoUrl() }}"
                         alt="{{ $institution->brandName() }}"
                         class="max-h-32 sm:max-h-40 max-w-xs sm:max-w-md w-auto object-contain mx-auto mb-8">
                @endif
                <h1 class="text-4xl font-bold text-gray-800 mb-4">{{ $institution->brandName() }}</h1>
                @if($institution->taglineLine() !== '')
                    <p class="text-xl text-gray-600 mb-8">{{ $institution->taglineLine() }}</p>
                @endif
                
                <div class="space-y-4">
                    <p class="text-gray-500">A comprehensive campus management system for educational institutions</p>
                    
                    @guest
                        <div class="mt-8 space-x-4">
                            <a href="{{ route('login') }}" class="inline-flex items-center px-6 py-3 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Login
                            </a>
                        </div>
                    @endguest
                </div>
            </div>
        </div>
    </div>
</body>
</html>
