@props(['variant' => 'notice'])

@php
    /**
     * The Master Data guidance for how departments must be set up.
     *
     * One wording in one file, shown in the two shapes the department pages
     * already use: the blue note block the forms carry at the foot, and the
     * small grey hint the fields carry underneath them. The listing and both
     * forms include this rather than each spelling the rule out, so the
     * three cannot drift apart.
     *
     * The names are read from the student type mapping itself rather than
     * typed out here. Nothing about that mapping is changed by reading it,
     * and this text can never name a department the mapping does not.
     */
    $required = \App\Support\AcademicPlacement::requiredDepartmentNames();

    // "Hifz, School and Dars-e-Nizami" - the last one joined with "and"
    // rather than a comma, however many there turn out to be.
    $last = array_pop($required);
    $requiredList = $required === [] ? $last : implode(', ', $required).' and '.$last;
@endphp

@if($variant === 'field')
    {{-- The same small hint the Department Code field already carries. --}}
    <p class="mt-1 text-xs text-gray-500">
        Create each programme as its own department. A student type that covers two
        programmes &mdash; such as Hifz + School, or Dars-e-Nizami + Computer &mdash;
        uses the two existing departments and must never be created as one combined
        department.
    </p>
@else
    <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <div class="flex">
            <svg class="w-5 h-5 text-blue-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
            <div class="ml-3">
                <p class="text-sm font-medium text-blue-800">Setting up departments</p>
                <p class="text-sm text-blue-800 mt-1">
                    Create each programme as its own department. A student type that covers two
                    programmes is a combination of departments that already exist here, and must
                    never be created as one combined department of its own:
                    <span class="font-medium">Hifz + School</span> is the Hifz department and the
                    School department, and
                    <span class="font-medium">Dars-e-Nizami + Computer</span> is the Dars-e-Nizami
                    department and the Computer department.
                </p>
                <p class="text-sm text-blue-800 mt-1">
                    Student Management fills in a student's department from their student type, so
                    {{ $requiredList }} each need a department on this page, named exactly that and
                    left active, for it to work.
                </p>
            </div>
        </div>
    </div>
@endif
