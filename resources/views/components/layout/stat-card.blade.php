@props(['title', 'value', 'subtitle' => null, 'icon' => 'chart-bar', 'color' => 'blue'])

@php
$colorClasses = [
    'blue' => 'bg-blue-100 text-blue-600',
    'green' => 'bg-green-100 text-green-600',
    'purple' => 'bg-purple-100 text-purple-600',
    'yellow' => 'bg-yellow-100 text-yellow-600',
    'red' => 'bg-red-100 text-red-600',
];
@endphp

<div class="bg-white rounded-lg shadow-sm p-6">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-gray-600 mb-1">{{ $title }}</p>
            <p class="text-3xl font-bold text-gray-900">{{ $value }}</p>
            @if($subtitle)
                <p class="text-sm text-gray-600 mt-2">{{ $subtitle }}</p>
            @endif
        </div>
        <div class="w-12 h-12 {{ $colorClasses[$color] ?? $colorClasses['blue'] }} rounded-lg flex items-center justify-center">
            <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-6 h-6" />
        </div>
    </div>
</div>
