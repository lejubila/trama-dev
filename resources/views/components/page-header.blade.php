@props(['title', 'subtitle' => null])

<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-slate-100">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">{{ $subtitle }}</p>
        @endif
    </div>
    @if (trim($slot) !== '')
        <div class="flex items-center gap-x-2">{{ $slot }}</div>
    @endif
</div>
