<div x-data="{ openLocal: false }" @click.outside="openLocal = false" class="relative w-72">
    <input
        type="text"
        wire:model.live.debounce.300ms="query"
        @focus="openLocal = true"
        @keydown.escape="openLocal = false; $wire.clear()"
        placeholder="Cerca dispositivi, interfacce, sedi…"
        class="block w-full rounded-md border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
        aria-label="Ricerca globale"
    />

    @if ($open && $query !== '')
        <div
            x-show="openLocal"
            x-cloak
            class="absolute right-0 z-50 mt-2 w-96 rounded-md bg-white dark:bg-slate-800 shadow-lg ring-1 ring-black ring-opacity-5 dark:ring-slate-600 max-h-96 overflow-y-auto"
        >
            @if ($totalHits === 0)
                <div class="p-4 text-sm text-gray-500 dark:text-slate-400">Nessun risultato.</div>
            @else
                @foreach ($groups as $kind => $items)
                    @if ($items->isNotEmpty())
                        <div class="border-b border-gray-100 dark:border-slate-700 last:border-0">
                            <div class="px-3 pt-2 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">
                                {{ ucfirst($kind) }}
                            </div>
                            <ul>
                                @foreach ($items as $item)
                                    <li>
                                        <a href="{{ $item['url'] }}"
                                           wire:navigate
                                           wire:click="clear"
                                           class="block px-3 py-2 hover:bg-gray-100 dark:hover:bg-slate-700">
                                            <div class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ $item['label'] }}</div>
                                            @if ($item['meta'] !== '')
                                                <div class="text-xs text-gray-500 dark:text-slate-400">{{ $item['meta'] }}</div>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            @endif
        </div>
    @endif
</div>
