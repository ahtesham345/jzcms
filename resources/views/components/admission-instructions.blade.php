@props([
    // Whose instructions to show. Give it a student when there is one, an
    // application when the placement has not been made yet, a student type
    // when neither exists, or an explicit list when the caller has already
    // resolved it - the public form does, because it resolves every type at
    // once. With none of them it falls back to the institution's defaults,
    // which is what this component always showed.
    'student' => null,
    'application' => null,
    'studentType' => null,
    'items' => null,
])

@php
    /**
     * The instructions a guardian agrees to.
     *
     * Still the one component every page renders. What changed is where the
     * wording comes from: each department now has its own set, and a student
     * placed in two is shown both, madrassa first, with shared lines shown
     * once. None of that is decided here - StudentTerms decides it, so the
     * three pages that show instructions cannot disagree.
     *
     * The heading and the agreement sentence stay global: they name the
     * section and state consent, and neither depends on what is studied.
     */
    $resolved = match (true) {
        is_array($items) => $items,
        $student !== null => \App\Support\StudentTerms::forStudent($student),
        $application !== null => \App\Support\StudentTerms::forApplication($application),
        $studentType !== null => \App\Support\StudentTerms::forStudentType($studentType),
        default => \App\Support\StudentTerms::defaults(),
    };
@endphp

<div dir="rtl" lang="ur" {{ $attributes->merge(['class' => 'text-right']) }}>
    <h4 class="text-base font-bold text-gray-900 mb-3 leading-loose">
        {{ \App\Support\StudentTerms::heading() }}
    </h4>

    <ul class="space-y-2 list-none">
        @foreach($resolved as $instruction)
            <li class="flex items-start text-gray-800 leading-loose">
                <span class="ml-2 select-none">&bull;</span>
                <span>{{ $instruction }}</span>
            </li>
        @endforeach
    </ul>
</div>
