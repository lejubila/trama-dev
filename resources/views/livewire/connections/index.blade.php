<div>
    <x-page-header title="Connessioni" subtitle="Cavi fisici tra le interfacce">
        @can('create', App\Models\Connection::class)
            <a href="{{ route('connections.create') }}" wire:navigate class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                <x-icon name="plus" class="h-4 w-4" /> Nuova connessione
            </a>
        @endcan
    </x-page-header>

    <div class="flex flex-wrap gap-3 mb-4 items-center">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cerca per etichetta…" class="rounded-md border-gray-300 shadow-sm text-sm w-64" />
        <select wire:model.live="equipmentFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="0">Tutti i dispositivi</option>
            @foreach ($equipmentList as $eq)
                <option value="{{ $eq->id }}">{{ $eq->name }}</option>
            @endforeach
        </select>
        <input type="text" wire:model.live.debounce.300ms="portFilter" placeholder="Nome porta (es. Gi0/1)…" class="rounded-md border-gray-300 shadow-sm text-sm w-56 font-mono" />
        <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">Tutti gli stati</option>
            @foreach ($statuses as $s)
                <option value="{{ $s->value }}">{{ $s->value }}</option>
            @endforeach
        </select>
        @if ($search !== '' || $statusFilter !== '' || $equipmentFilter > 0 || $portFilter !== '')
            <button wire:click="clearFilters" type="button" class="text-xs text-gray-500 hover:text-gray-700 underline">Reset filtri</button>
        @endif
    </div>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estremo A</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estremo B</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cavo</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Colore</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Etichetta</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stato</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Azioni</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                @forelse ($connections as $c)
                    <tr wire:key="cn-{{ $c->id }}">
                        <td class="px-4 py-3">
                            <a href="{{ route('equipment.show', $c->fromInterface->equipment) }}" wire:navigate class="text-indigo-700 hover:underline">{{ $c->fromInterface->equipment->name }}</a>
                            <span class="text-gray-400">·</span>
                            <span class="font-mono text-gray-700">{{ $c->fromInterface->name }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('equipment.show', $c->toInterface->equipment) }}" wire:navigate class="text-indigo-700 hover:underline">{{ $c->toInterface->equipment->name }}</a>
                            <span class="text-gray-400">·</span>
                            <span class="font-mono text-gray-700">{{ $c->toInterface->name }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $c->cable_type }} {{ $c->cable_length_m ? '· '.$c->cable_length_m.' m' : '' }}</td>
                        <td class="px-4 py-3 text-gray-600">
                            @if ($c->color)
                                <span class="inline-flex items-center gap-x-1.5">
                                    <span class="inline-block h-4 w-4 rounded border border-gray-300" style="background-color: {{ $c->color }}" title="{{ $c->color }}"></span>
                                    <span class="font-mono text-xs">{{ strtoupper($c->color) }}</span>
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $c->cable_label ?? '—' }}
                            @if ($c->tags->isNotEmpty())
                                <div class="mt-1"><x-tag-chips :tags="$c->tags" /></div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $c->status?->value }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            @can('update', $c)
                                <a href="{{ route('connections.edit', $c) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800 text-xs">Modifica</a>
                                @if ($c->status?->value === 'active')
                                    <button wire:click="decommission({{ $c->id }})" wire:confirm="Dismettere questa connessione?" class="text-amber-600 hover:text-amber-800 text-xs">Dismetti</button>
                                @endif
                            @endcan
                            @can('delete', $c)
                                <button wire:click="delete({{ $c->id }})" wire:confirm="Eliminare la connessione?" class="text-red-600 hover:text-red-800"><x-icon name="trash" class="h-4 w-4 inline" /></button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">Nessuna connessione.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $connections->links() }}</div>
</div>
