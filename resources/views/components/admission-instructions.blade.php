{{-- Reads from config/admission_instructions.php: the wording lives there and
     is never duplicated in a view or controller. --}}
<div dir="rtl" lang="ur" {{ $attributes->merge(['class' => 'text-right']) }}>
    <h4 class="text-base font-bold text-gray-900 mb-3 leading-loose">
        {{ config('admission_instructions.heading') }}
    </h4>

    <ul class="space-y-2 list-none">
        @foreach(config('admission_instructions.items') as $instruction)
            <li class="flex items-start text-gray-800 leading-loose">
                <span class="ml-2 select-none">•</span>
                <span>{{ $instruction }}</span>
            </li>
        @endforeach
    </ul>
</div>
