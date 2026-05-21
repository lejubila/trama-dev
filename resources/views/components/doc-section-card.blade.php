@props([
    'title',
    'enabledModel',
    'descriptionModel',
    'selectAllTarget',
    'count' => 0,
    'total' => 0,
])

<div class="bg-white shadow ring-1 ring-black/5 rounded-md">
    <div class="p-4 border-b border-gray-200 flex items-center justify-between">
        <label class="inline-flex items-center gap-3">
            <input type="checkbox" wire:model.live="{{ $enabledModel }}"
                   class="rounded border-gray-300 text-indigo-600">
            <span class="font-semibold text-gray-700">{{ $title }}</span>
        </label>
        <div class="flex items-center gap-3 text-xs text-gray-500">
            <span>Selezionati: {{ $count }} / {{ $total }}</span>
            <button type="button" wire:click="selectAll('{{ $selectAllTarget }}')"
                    class="text-indigo-600 hover:underline">Tutti</button>
            <button type="button" wire:click="selectNone('{{ $selectAllTarget }}')"
                    class="text-gray-600 hover:underline">Nessuno</button>
        </div>
    </div>
    <div class="p-4 space-y-3 @if (!$attributes->get('enabled', $enabledModel)) @endif">
        <div>
            <label class="block text-sm font-medium text-gray-700">Descrizione di sezione</label>
            <textarea wire:model="{{ $descriptionModel }}" rows="2"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
        </div>
        <div class="max-h-72 overflow-y-auto border border-gray-200 rounded p-2 grid grid-cols-1 sm:grid-cols-2 gap-x-4">
            {{ $items }}
        </div>
    </div>
</div>
