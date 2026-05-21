<div>
    <div
        x-data="{ open: @entangle('open') }"
        x-show="open"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
    >
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl w-full max-w-lg mx-4 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100 mb-4">Salva snapshot della topologia</h3>

            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Titolo</label>
                    <input type="text" wire:model="title" autofocus
                           class="w-full rounded-md border-gray-300 dark:bg-slate-900 dark:border-slate-600 shadow-sm text-sm" />
                    @error('title') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Descrizione (opzionale)</label>
                    <textarea wire:model="description" rows="3"
                              class="w-full rounded-md border-gray-300 dark:bg-slate-900 dark:border-slate-600 shadow-sm text-sm"></textarea>
                    @error('description') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Data</label>
                    <input type="date" wire:model="snapshotDate"
                           class="rounded-md border-gray-300 dark:bg-slate-900 dark:border-slate-600 shadow-sm text-sm" />
                    @error('snapshotDate') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="text-xs text-gray-500 dark:text-slate-400">
                    @if ($snapshotImageBase64 !== '')
                        Immagine pronta ({{ round(strlen($snapshotImageBase64) * 3 / 4 / 1024) }} KB circa).
                    @else
                        In attesa della cattura PNG…
                    @endif
                    @error('snapshotImageBase64') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" wire:click="close"
                            class="px-3 py-1.5 text-sm rounded border bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-200">Annulla</button>
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            :disabled="!$wire.snapshotImageBase64"
                            x-data
                            class="px-3 py-1.5 text-sm rounded bg-indigo-600 text-white disabled:opacity-50">
                        Salva
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
