<x-layout.admin title="Add Daily Record">
    <x-slot name="header">
        Hifz &amp; Quran — Add Daily Record
    </x-slot>

    <div class="space-y-6">
        <div>
            <a href="{{ route('hifz.index', $returnFilters) }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Hifz &amp; Quran
            </a>
        </div>

        @include('hifz.partials.form', ['record' => null])
    </div>
</x-layout.admin>
