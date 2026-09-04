@props(['title' => 'Print', 'back' => null])

{{--
    A bare page for printing. No sidebar, no navbar, no filters and no
    footer: what reaches the paper is the register itself.

    The screen still shows a toolbar so the page is usable before it is
    printed. Everything in it carries print:hidden, so none of it is on the
    printed sheet.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - {{ \App\Models\Setting::current()->brandName() }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        @media print {
            html, body {
                background: #fff;
            }

            /* Keep a long register readable across page breaks. */
            table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            thead {
                display: table-header-group;
            }
        }
    </style>
</head>
<body class="font-sans antialiased bg-gray-100 print:bg-white">
    <!-- Screen-only toolbar -->
    <div class="print:hidden bg-white border-b border-gray-200">
        <div class="max-w-full mx-auto px-6 py-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-4">
                @if($back)
                    <a href="{{ $back }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back
                    </a>
                @endif
                <span class="text-sm text-gray-500">Use your browser's print dialog to print or save as PDF.</span>
            </div>

            <button type="button" onclick="window.print()"
                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print
            </button>
        </div>
    </div>

    <div class="p-6 print:p-0">
        <div class="bg-white print:bg-transparent rounded-lg print:rounded-none shadow-sm print:shadow-none p-6 print:p-0">
            <!-- Institution heading -->
            {{-- The same letterhead the PDFs carry, in the browser's own
                 print output: logo when one is on file, then the
                 institution name and its tagline. This page is printed by
                 the browser rather than by dompdf, so the Urdu script
                 shapes correctly here and the institution's own default
                 language is safe to follow. --}}
            @php($institution = \App\Models\Setting::current())
            <div class="text-center mb-4">
                @if($institution->hasLogo())
                    <img src="{{ $institution->logoUrl() }}"
                         alt="{{ $institution->brandName() }}"
                         class="max-h-16 max-w-xs w-auto object-contain mx-auto mb-2">
                @endif
                <h1 class="text-xl font-bold text-gray-900">{{ $institution->brandName() }}</h1>
                @if($institution->taglineLine() !== '')
                    <p class="text-xs text-gray-600">{{ $institution->taglineLine() }}</p>
                @endif
                <p class="text-base font-semibold text-gray-800 mt-1">{{ $heading ?? $title }}</p>
            </div>

            {{ $slot }}

            <div class="mt-6 text-xs text-gray-500 flex justify-between">
                <span>Printed {{ now()->format('d M, Y H:i') }}</span>
                <span>{{ $institution->brandName() }}</span>
            </div>
        </div>
    </div>
</body>
</html>
