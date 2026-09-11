@php
    /**
     * The Settings module's own navigation.
     *
     * In the page rather than in the sidebar, which carries one top-level
     * Settings link and no dropdown. The first two pages write the same
     * singleton row and the third writes one row per department; all three
     * are the institution's configuration, not three settings systems.
     */
    $tabs = [
        ['route' => 'settings.edit', 'label' => 'General Settings'],
        ['route' => 'settings.admission-form.edit', 'label' => 'Admission Form Settings'],
        ['route' => 'settings.student-terms.edit', 'label' => 'Student Terms / Instructions'],
    ];
@endphp

<div class="bg-white rounded-lg shadow-sm">
    <nav class="flex flex-wrap gap-1 px-3 py-2" aria-label="Settings sections">
        @foreach($tabs as $tab)
            @php($isActive = request()->routeIs($tab['route']))
            <a
                href="{{ route($tab['route']) }}"
                @if($isActive) aria-current="page" @endif
                class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ $isActive ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}"
            >
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
