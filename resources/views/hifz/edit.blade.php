<x-layout.admin title="Edit Daily Record">
    <x-slot name="header">
        Hifz &amp; Quran — Edit Daily Record
    </x-slot>

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <a href="{{ route('hifz.index', $returnFilters) }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Hifz &amp; Quran
            </a>

            <a href="{{ route('hifz.show', $record->id) }}" class="text-sm text-blue-600 hover:text-blue-800">
                View this record
            </a>
        </div>

        {{-- The record stays on the enrollment it was written against. It
             cannot be moved to another student or to another track, so the
             class and section it shows are the ones it was made under even
             after the student has been promoted. --}}
        @include('hifz.partials.form', ['recordDate' => $record->record_date?->format('Y-m-d')])
    </div>
</x-layout.admin>
