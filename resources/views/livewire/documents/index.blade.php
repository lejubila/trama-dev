<div>
    <x-page-header title="Documentazione" subtitle="Documenti PDF generati per il cliente">
        @can('create', App\Models\Document::class)
            <a href="{{ route('documents.create') }}" wire:navigate
               class="text-sm px-3 py-1.5 rounded bg-indigo-600 text-white hover:bg-indigo-700">
                + Nuovo documento
            </a>
        @endcan
    </x-page-header>

    <div class="flex flex-wrap items-end gap-3 p-3 bg-white shadow ring-1 ring-black/5 rounded-md mb-4">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Cerca titolo</label>
            <input type="text" wire:model.live.debounce.400ms="search"
                   class="rounded-md border-gray-300 shadow-sm text-sm" />
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Dal</label>
            <input type="date" wire:model.live="dateFrom"
                   class="rounded-md border-gray-300 shadow-sm text-sm" />
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Al</label>
            <input type="date" wire:model.live="dateTo"
                   class="rounded-md border-gray-300 shadow-sm text-sm" />
        </div>
        <button wire:click="clearFilters"
                class="text-xs px-2 py-1 text-gray-500 hover:text-gray-700 underline ml-auto">
            Reset filtri
        </button>
    </div>

    @if ($documents->isEmpty())
        <div class="bg-white shadow ring-1 ring-black/5 rounded-md p-8 text-center text-sm text-gray-500">
            Nessun documento.
            @can('create', App\Models\Document::class)
                <a href="{{ route('documents.create') }}" wire:navigate class="text-indigo-600 hover:underline">Creane uno nuovo</a>.
            @endcan
        </div>
    @else
        <div class="bg-white shadow ring-1 ring-black/5 rounded-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Titolo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Generato</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Autore</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Azioni</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($documents as $doc)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $doc->title }}</div>
                                @if ($doc->description)
                                    <div class="text-xs text-gray-500 truncate max-w-md">{{ $doc->description }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $doc->document_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                @if ($doc->generated_at)
                                    {{ $doc->generated_at->diffForHumans() }}
                                @else
                                    <span class="text-gray-400 italic">mai</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $doc->creator?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right space-x-2">
                                @if ($doc->pdf_path)
                                    <a href="{{ route('documents.pdf', ['document' => $doc, 'v' => $doc->generated_at?->getTimestamp()]) }}"
                                       class="text-indigo-600 hover:text-indigo-800 text-sm" target="_blank">
                                        Apri PDF
                                    </a>
                                @endif
                                @can('update', $doc)
                                    <a href="{{ route('documents.edit', $doc) }}" wire:navigate
                                       class="text-gray-600 hover:text-gray-800 text-sm">Modifica</a>
                                    <button wire:click="regenerate({{ $doc->id }})"
                                            wire:confirm="Rigenerare il PDF?"
                                            class="text-gray-600 hover:text-gray-800 text-sm">Rigenera</button>
                                @endcan
                                @can('delete', $doc)
                                    <button wire:click="delete({{ $doc->id }})"
                                            wire:confirm="Eliminare definitivamente questo documento?"
                                            class="text-red-600 hover:text-red-800 text-sm">Elimina</button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $documents->links() }}
        </div>
    @endif
</div>
