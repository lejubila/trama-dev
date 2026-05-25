<div
    x-data="{ fs: false, filters: false }"
    x-effect="$dispatch('topology-fullscreen', { on: fs }); $nextTick(() => setTimeout(() => { if (window.cy) { window.cy.resize(); window.cy.fit(undefined, 30); } }, 60))"
    @topology-toggle-fs.window="fs = !fs"
    @topology-toggle-filters.window="filters = !filters"
    @keydown.escape.window="fs && (fs = false)"
    :class="fs ? 'fixed inset-0 z-50 bg-gray-100 dark:bg-slate-900 p-3' : ''"
    :style="fs ? 'height: 100vh' : 'height: calc(100vh - 8rem)'"
    class="flex flex-col"
>
    <div x-show="!fs">
        <x-page-header title="Topologia" subtitle="Vista grafico delle connessioni del cliente attivo" />
    </div>

    {{-- Filtri: sempre visibili in modalità standard; a schermo intero
         compaiono solo quando si attiva il toggle (in alto a sinistra sulla mappa). --}}
    <div
        x-show="!fs || filters"
        class="flex flex-col gap-3 p-3 bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md mb-3"
    >
        {{-- Riga 1: filtri --}}
        <div class="flex flex-wrap items-center gap-3">
        <select wire:model.live="siteId" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="0">Tutte le sedi</option>
            @foreach ($sites as $s)
                <option value="{{ $s->id }}">{{ $s->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="roomFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="0">Tutti i locali</option>
            @foreach ($rooms as $r)
                <option value="{{ $r->id }}">{{ $r->name }}@if ($siteId === 0 && $r->site) ({{ $r->site->name }})@endif</option>
            @endforeach
        </select>

        <select wire:model.live="layout" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="cose-bilkent">Force-directed (cose-bilkent)</option>
            <option value="dagre">Gerarchico (dagre)</option>
            <option value="breadthfirst">Albero (breadthfirst)</option>
            <option value="circle">Circolare</option>
            <option value="grid">Griglia</option>
        </select>

        <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">Tutti gli stati</option>
            @foreach ($statuses as $s)
                <option value="{{ $s->value }}">{{ $s->label() }}</option>
            @endforeach
        </select>

        <div x-data="{ open: false }" @click.away="open = false" class="relative">
            <button type="button" @click="open = !open"
                class="rounded-md border border-gray-300 shadow-sm text-sm px-3 py-2 bg-white inline-flex items-center gap-1">
                Tipi @if (count($filterTypes)) <span class="text-indigo-600 font-medium">({{ count($filterTypes) }})</span> @endif
                <span class="text-gray-400">▾</span>
            </button>
            <div x-show="open" x-cloak class="absolute z-20 mt-1 max-h-80 overflow-auto rounded-md bg-white p-2 shadow-lg ring-1 ring-black/5 w-56">
                @if (count($filterTypes))
                    <button type="button" wire:click="$set('filterTypes', [])"
                        class="block w-full text-left px-2 py-1 text-xs text-indigo-600 hover:underline">Seleziona tutti</button>
                @endif
                @foreach ($types as $t)
                    @php $checked = $filterTypes === [] || in_array($t->value, $filterTypes, true); @endphp
                    <button type="button" wire:click="toggleType('{{ $t->value }}')"
                        wire:key="ft-{{ $t->value }}"
                        class="w-full flex items-center gap-2 px-2 py-1 text-sm hover:bg-gray-50 text-left">
                        <span class="inline-flex h-4 w-4 items-center justify-center rounded border {{ $checked ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-gray-300 bg-white' }}">
                            @if ($checked) <span class="text-xs leading-none">✓</span> @endif
                        </span>
                        <span class="inline-block h-2.5 w-2.5 rounded-full bg-{{ $t->color() }}-500"></span>
                        {{ $t->label() }}
                    </button>
                @endforeach
            </div>
        </div>

        <div x-data="{ open: false }" @click.away="open = false" class="relative">
            <button type="button" @click="open = !open"
                class="rounded-md border border-gray-300 shadow-sm text-sm px-3 py-2 bg-white inline-flex items-center gap-1">
                Tag @if (count($tagFilters)) <span class="text-indigo-600 font-medium">({{ count($tagFilters) }})</span> @endif
                <span class="text-gray-400">▾</span>
            </button>
            <div x-show="open" x-cloak class="absolute z-20 mt-1 max-h-60 overflow-auto rounded-md bg-white p-2 shadow-lg ring-1 ring-black/5 min-w-[12rem]">
                @forelse ($allTags as $tag)
                    <label class="flex items-center gap-2 px-2 py-1 text-sm cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" value="{{ $tag->id }}" wire:model.live="tagFilters"
                            class="rounded border-gray-300 text-indigo-600" />
                        <span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: {{ $tag->color }}"></span>
                        {{ $tag->name }}
                    </label>
                @empty
                    <span class="block px-2 py-1 text-xs text-gray-400">Nessun tag</span>
                @endforelse
            </div>
        </div>

        <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
            <span class="font-medium">VLAN</span>
            <input
                type="number" min="1" max="4094"
                wire:model.live.debounce.500ms="vlanFilter"
                placeholder="es. 10"
                class="w-24 rounded-md border-gray-300 shadow-sm text-sm"
            />
        </label>
        </div>

        {{-- Riga 2: flag a sinistra, azioni allineate a destra --}}
        <div class="flex flex-wrap items-center gap-3">
        <label class="inline-flex items-center gap-1 text-xs text-gray-700" title="Includi gli apparati con il flag 'Nascosto nella topologia'">
            <input type="checkbox" wire:model.live="includeHidden" class="rounded border-gray-300 text-indigo-600" />
            Mostra nascosti
        </label>

        <label class="inline-flex items-center gap-1 text-xs text-gray-700" title="Collassa patch panel e prese a muro: mostra un edge end-to-end tra i terminali etichettato con il path attraversato">
            <input type="checkbox" wire:model.live="hidePatchPanels" class="rounded border-gray-300 text-indigo-600" />
            Nascondi patch panel / prese
        </label>

        <label class="inline-flex items-center gap-1 text-xs text-gray-700" title="Racchiude gli apparati di ogni rack in un contenitore visivo">
            <input type="checkbox" wire:model.live="groupByRack" class="rounded border-gray-300 text-indigo-600" />
            Raggruppa per rack
        </label>

        <label class="inline-flex items-center gap-1 text-xs text-gray-700" title="Racchiude gli apparati dello stesso locale in un contenitore visivo">
            <input type="checkbox" wire:model.live="groupByRoom" class="rounded border-gray-300 text-indigo-600" />
            Raggruppa per locale
        </label>

        <label class="inline-flex items-center gap-1 text-xs text-gray-700" title="Racchiude rack/locali della stessa sede in un contenitore visivo">
            <input type="checkbox" wire:model.live="groupBySite" class="rounded border-gray-300 text-indigo-600" />
            Raggruppa per sede
        </label>

        <button wire:click="clearFilters" class="text-xs px-2 py-1 text-gray-500 hover:text-gray-700 underline ml-auto">
            Reset filtri
        </button>

        <div class="flex items-center gap-1">
            <button x-data x-on:click="window.cy && window.cy.fit()" class="text-xs px-2 py-1 rounded border bg-white text-gray-700">⛶ Fit</button>
            <button x-data x-on:click="window.cy && window.cy.zoom(window.cy.zoom() * 1.2)" class="text-xs px-2 py-1 rounded border bg-white text-gray-700">+</button>
            <button x-data x-on:click="window.cy && window.cy.zoom(window.cy.zoom() / 1.2)" class="text-xs px-2 py-1 rounded border bg-white text-gray-700">−</button>
            <button x-data x-on:click="window.dispatchEvent(new CustomEvent('topology:export-png'))" class="text-xs px-2 py-1 rounded border bg-white text-gray-700">PNG</button>

            @if ($canEdit)
                <button
                    x-data
                    x-on:click="Livewire.dispatch('snapshot-open', { viewState: {
                        siteId: $wire.siteId,
                        roomFilter: $wire.roomFilter,
                        statusFilter: $wire.statusFilter,
                        vlanFilter: $wire.vlanFilter,
                        tagFilters: $wire.tagFilters,
                        layout: $wire.layout,
                        filterTypes: $wire.filterTypes,
                        includeHidden: $wire.includeHidden,
                        groupByRack: $wire.groupByRack,
                        groupBySite: $wire.groupBySite,
                        groupByRoom: $wire.groupByRoom,
                        hidePatchPanels: $wire.hidePatchPanels,
                        nodePositions: window.cy
                            ? Object.fromEntries(window.cy.nodes().map(n => {
                                const p = n.position();
                                return [n.id(), [Math.round(p.x), Math.round(p.y)]];
                              }))
                            : {},
                        zoom: window.cy ? window.cy.zoom() : 1,
                        pan:  window.cy ? [Math.round(window.cy.pan().x), Math.round(window.cy.pan().y)] : [0, 0],
                    }})"
                    class="text-xs px-2 py-1 rounded border bg-white text-gray-700"
                >Salva snapshot</button>
            @endif
        </div>
        </div>
    </div>

    @if ($canEdit)
        <livewire:topology.snapshot-save-modal />
    @endif

    <div
        x-data="topologyGraph({
            graph: @js($graph),
            layout: @js($layout),
            iconSize: {{ $topologyIconSize }},
            restore: @js($restore ?? null),
        })"
        x-init="init($el)"
        wire:ignore
        class="flex-1 relative bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden"
    >
        <div x-ref="cy" class="absolute inset-0"></div>

        {{-- Toggle filtri: visibile solo a schermo intero, ancorato in alto a
             sinistra dentro la mappa. filters vive nel wrapper esterno → evento window. --}}
        <button
            type="button"
            x-show="fs"
            @click="$dispatch('topology-toggle-filters')"
            :class="filters ? 'bg-indigo-50 border-indigo-300 text-indigo-700' : 'bg-white text-gray-700 border-gray-200'"
            class="absolute top-2 left-2 z-20 rounded border shadow px-3 py-1.5 text-xs inline-flex items-center gap-1"
            style="display: none;"
        >
            <span>Filtri e raggruppamenti</span>
            <span x-text="filters ? '▴' : '▾'"></span>
        </button>
        {{-- id + cytoscape-navigator class: the extension only reuses an existing
             element when `container` is a string selector (a DOM element makes it
             build its own panel on <body>). The cytoscape-navigator class lets the
             package's child styles (img/canvas/view) apply; topology-navigator is
             our size/position override. --}}
        <div x-ref="navigator" id="topology-navigator" class="cytoscape-navigator topology-navigator" style="display: none;"></div>

        <div
            class="absolute top-2 right-2 z-10 bg-white/95 border border-gray-200 rounded shadow px-3 py-2 text-xs flex items-center gap-2 select-none"
        >
            <button
                type="button"
                @click="toggleNavigator()"
                :class="showNavigator ? 'bg-indigo-50 border-indigo-300 text-indigo-700' : 'bg-white border-gray-200 text-gray-600'"
                class="rounded border shadow-sm px-2 py-1 text-xs whitespace-nowrap"
                title="Mostra/nascondi mini-mappa"
            >🗺️ Mappa</button>

            {{-- Toggle schermo intero. fs vive nel wrapper esterno: usiamo un
                 evento window per non creare una proprietà ombra in questo scope. --}}
            <button
                type="button"
                @click="$dispatch('topology-toggle-fs')"
                :class="fs ? 'bg-indigo-50 border-indigo-300 text-indigo-700' : 'bg-white border-gray-200 text-gray-600'"
                class="rounded border shadow-sm px-2 py-1 text-xs whitespace-nowrap"
                :title="fs ? 'Esci da schermo intero' : 'Schermo intero'"
                x-text="fs ? '✕ Schermo' : '⛶ Schermo'"
            ></button>

            @if ($canEdit)
                <span class="text-gray-600 pointer-events-none">Dim. icone</span>
                <input
                    type="range"
                    min="{{ $minIconPx }}" max="{{ $maxIconPx }}" step="1"
                    x-model.number="globalIconSize"
                    @input="applyGlobalSize($event.target.value)"
                    @change="persistGlobalSize()"
                    @pointerdown.stop
                    class="w-40 cursor-pointer"
                />
                <span class="font-mono text-gray-700 pointer-events-none" x-text="globalIconSize + ' px'"></span>
            @endif
        </div>
    </div>

    <livewire:equipment.drawer />
</div>
