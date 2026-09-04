@php
    /**
     * The Settings module's own navigation.
     *
     * In the page rather than in the sidebar, which carries one top-level
     * Settings link and no dropdown. Both pages write the same singleton
     * row; these are two views of it, not two settings systems.
     */
    $tabs = [
        ['route' => 'settings.edit', 'label' => 'General Settings'],
        ['route' => 'settings.admission-form.edit', 'label' => 'Admission Form Settings'],
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
