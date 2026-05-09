<div>
    <div class="flex items-center justify-between mb-3">
        <div class="text-sm text-gray-600">
            Click su un dispositivo per il dettaglio · trascina per riposizionare · click su slot vuoto per aggiungere
        </div>
        <div class="inline-flex rounded-md border border-gray-300 bg-white p-0.5 text-xs">
            <button
                wire:click="setOrient('front')"
                class="px-3 py-1 rounded {{ $orient === 'front' ? 'bg-indigo-600 text-white' : 'text-gray-600' }}"
            >Front</button>
            <button
                wire:click="setOrient('rear')"
                class="px-3 py-1 rounded {{ $orient === 'rear' ? 'bg-indigo-600 text-white' : 'text-gray-600' }}"
            >Rear</button>
        </div>
    </div>

    <div
        x-data="rackDnD"
        x-init="init($el)"
        class="bg-gray-50 rounded-md p-4 ring-1 ring-black ring-opacity-5 overflow-x-auto"
    >
        <x-rack-elevation :rack="$rack" :orient="$orient" />
        <div
            x-ref="ghost"
            x-show="dragging"
            x-cloak
            class="pointer-events-none absolute z-50 px-2 py-1 text-xs font-medium rounded bg-indigo-600 text-white shadow"
            x-text="hint"
        ></div>
    </div>
</div>
