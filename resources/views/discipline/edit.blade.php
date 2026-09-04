<x-layout.admin title="Edit Discipline Record">
    <x-slot name="header">
        Discipline — Edit Record
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center gap-4">
            <a href="{{ route('discipline.index', $returnFilters) }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Discipline
            </a>

            <a href="{{ route('discipline.show', $record->id) }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Record
            </a>
        </div>

        @include('discipline.partials.form')
    </div>
</x-layout.admin>
