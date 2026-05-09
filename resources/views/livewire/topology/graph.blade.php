<div class="flex flex-col" style="height: calc(100vh - 8rem);">
    <x-page-header title="Topologia" subtitle="Vista grafico delle connessioni del cliente attivo" />

    <div class="flex flex-wrap items-center gap-3 p-3 bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md mb-3">
        <select wire:model.live="siteId" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="0">Tutte le sedi</option>
            @foreach ($sites as $s)
                <option value="{{ $s->id }}">{{ $s->name }}</option>
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

        <input
            type="number" min="1" max="4094"
            wire:model.live.debounce.500ms="vlanFilter"
            placeholder="VLAN"
            class="w-24 rounded-md border-gray-300 shadow-sm text-sm"
        />

        <div class="flex flex-wrap gap-1">
            @foreach ($types as $t)
                <button
                    type="button"
                    wire:click="toggleType('{{ $t->value }}')"
                    class="text-xs px-2 py-1 rounded border {{ in_array($t->value, $filterTypes, true) ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300' }}"
                >{{ $t->label() }}</button>
            @endforeach
        </div>

        <button wire:click="clearFilters" class="text-xs px-2 py-1 text-gray-500 hover:text-gray-700 underline ml-auto">
            Reset filtri
        </button>

        <div class="flex items-center gap-1">
            <button x-data x-on:click="window.cy && window.cy.fit()" class="text-xs px-2 py-1 rounded border bg-white text-gray-700">⛶ Fit</button>
            <button x-data x-on:click="window.cy && window.cy.zoom(window.cy.zoom() * 1.2)" class="text-xs px-2 py-1 rounded border bg-white text-gray-700">+</button>
            <button x-data x-on:click="window.cy && window.cy.zoom(window.cy.zoom() / 1.2)" class="text-xs px-2 py-1 rounded border bg-white text-gray-700">−</button>
            <button x-data x-on:click="window.dispatchEvent(new CustomEvent('topology:export-png'))" class="text-xs px-2 py-1 rounded border bg-white text-gray-700">PNG</button>
        </div>
    </div>

    <div
        x-data="topologyGraph({
            graph: @js($graph),
            layout: @js($layout),
        })"
        x-init="init($el)"
        wire:ignore
        class="flex-1 relative bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden"
    >
        <div x-ref="cy" class="absolute inset-0"></div>
        <div x-ref="navigator" class="absolute bottom-2 right-2 w-40 h-32 bg-white/95 border border-gray-200 rounded shadow"></div>
    </div>

    <livewire:equipment.drawer />
</div>
