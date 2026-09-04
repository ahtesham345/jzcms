{{-- $width lets wider pages (e.g. the admission form) opt out of the narrow
     auth card without affecting the login and password screens. --}}
@props(['width' => 'sm:max-w-md', 'title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title . ' - ' . \App\Models\Setting::current()->brandName() : \App\Models\Setting::current()->brandName() }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-50">
            <div>
                {{-- The institution's logo when one is on file, its
                     name when not. hasLogo() checks the file is still
                     there, so a deleted logo falls through to the name
                     rather than drawing a broken image. --}}
                @php($institution = \App\Models\Setting::current())
                <a href="/" class="text-center block">
                    @if($institution->hasLogo())
                        <img src="{{ $institution->logoUrl() }}"
                             alt="{{ $institution->brandName() }}"
                             class="max-h-16 max-w-xs w-auto object-contain mx-auto">
                    @endif
                    <h1 class="text-2xl font-bold text-gray-800 {{ $institution->hasLogo() ? 'mt-3' : '' }}">
                        {{ $institution->brandName() }}
                    </h1>
                    @if($institution->taglineLine() !== '')
                        <p class="text-sm text-gray-600 mt-1">{{ $institution->taglineLine() }}</p>
                    @endif
                </a>
            </div>

            <div class="w-full {{ $width }} mt-6 mb-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
