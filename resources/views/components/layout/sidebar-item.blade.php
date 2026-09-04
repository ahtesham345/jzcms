@props(['href' => '#', 'active' => false, 'icon' => 'circle'])

<a href="{{ $href }}" 
   {{ $attributes->merge(['class' => 'flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-150 ' . 
   ($active 
       ? 'bg-gray-800 text-white' 
       : 'text-gray-300 hover:bg-gray-800 hover:text-white')]) }}>
    <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-5 h-5 mr-3 flex-shrink-0" />
    <span class="min-w-0 truncate">{{ $slot }}</span>
</a>
