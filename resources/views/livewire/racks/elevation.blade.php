<div>
    <div class="flex items-center justify-between mb-3">
        <div class="text-sm text-gray-600 dark:text-slate-400">
            Click su un dispositivo per il dettaglio · trascina per riposizionare
            @if ($canEdit) · click su slot vuoto per aggiungere @endif
        </div>
        <div class="inline-flex rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 p-0.5 text-xs">
            <button
                wire:click="setOrient('front')"
                class="px-3 py-1 rounded {{ $orient === 'front' ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-slate-300' }}"
            >Front</button>
            <button
                wire:click="setOrient('rear')"
                class="px-3 py-1 rounded {{ $orient === 'rear' ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-slate-300' }}"
            >Rear</button>
        </div>
    </div>

    <div
        x-data="rackDnD"
        x-init="init($el)"
        class="bg-gray-50 dark:bg-slate-900 rounded-md p-4 ring-1 ring-black ring-opacity-5 overflow-x-auto"
    >
        <x-rack-elevation :rack="$rack" :orient="$orient" :interactive="$canEdit" />
        <div
            x-ref="ghost"
            x-show="dragging"
            x-cloak
            class="pointer-events-none absolute z-50 px-2 py-1 text-xs font-medium rounded bg-indigo-600 text-white shadow"
            x-text="hint"
        ></div>
    </div>

    @if ($showForm && $selectedU !== null)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" wire:click.self="closeForm">
            <div class="bg-white dark:bg-slate-800 rounded-md shadow-lg w-full max-w-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100 mb-1">Nuovo dispositivo · U{{ $selectedU }}</h2>
                <p class="text-xs text-gray-500 dark:text-slate-400 mb-4">Rack <span class="font-mono">{{ $rack->name }}</span> ({{ $rack->height_units }}U)</p>

                <form wire:submit="saveEquipment" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Nome</label>
                            <input type="text" wire:model="name" autofocus class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Tipo</label>
                            <select wire:model="type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm">
                                @foreach ($types as $t)
                                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                @endforeach
                            </select>
                            @error('type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Altezza (U)</label>
                            <input type="number" min="1" max="60" wire:model="positionUHeight" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                            @error('positionUHeight')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Posizione</label>
                            <input type="text" disabled value="U{{ $selectedU }}{{ $positionUHeight > 1 ? '–U'.($selectedU + $positionUHeight - 1) : '' }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-900 dark:text-slate-300 shadow-sm text-sm font-mono" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Vendor</label>
                            <input type="text" wire:model="vendor" placeholder="opzionale" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Modello</label>
                            <input type="text" wire:model="modelName" placeholder="opzionale" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-slate-400">Per i campi avanzati (seriale, firmware, IP management, ecc.) usa la pagina dispositivo dopo la creazione.</p>
                    <div class="flex justify-end gap-x-2 pt-2">
                        <button type="button" wire:click="closeForm" class="px-3 py-2 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-md">Annulla</button>
                        <button type="submit" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Crea</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
