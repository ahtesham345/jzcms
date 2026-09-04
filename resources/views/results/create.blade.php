<x-layout.admin title="Add Result">
    <x-slot name="header">
        Results — Add Result
    </x-slot>

    <div class="space-y-6">
        <div>
            <a href="{{ route('results.index', $returnFilters) }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Results
            </a>
        </div>

        @include('results.partials.form')
    </div>
</x-layout.admin>
