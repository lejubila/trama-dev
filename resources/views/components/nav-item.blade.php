@props(['href', 'active' => false, 'icon' => null])

@php
    $base = 'flex items-center gap-x-2 rounded-md px-2 py-1.5 text-sm transition';
    $classes = $active
        ? $base.' bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 font-medium'
        : $base.' text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700';
@endphp

<li>
    <a href="{{ $href }}" wire:navigate class="{{ $classes }}">
        @if ($icon)
            <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
        @endif
        <span>{{ $slot }}</span>
    </a>
</li>
