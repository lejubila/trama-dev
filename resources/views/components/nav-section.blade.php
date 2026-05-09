@props(['title'])

<div class="px-4 mb-6">
    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400 mb-2">{{ $title }}</h3>
    <ul class="space-y-1">
        {{ $slot }}
    </ul>
</div>
