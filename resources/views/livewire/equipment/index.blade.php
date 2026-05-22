<div>
    <x-page-header title="Dispositivi" subtitle="Tutti gli equipment del cliente attivo">
        <a href="{{ route('export.equipment.csv') }}" class="text-sm text-gray-700 hover:text-gray-900 inline-flex items-center gap-x-1">⤓ Esporta CSV</a>
        @can('create', App\Models\Equipment::class)
            <a href="{{ route('equipment.import') }}" wire:navigate class="text-sm text-gray-700 hover:text-gray-900 inline-flex items-center gap-x-1">⤒ Importa CSV</a>
            <button wire:click="openCreate" class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                <x-icon name="plus" class="h-4 w-4" /> Nuovo dispositivo
            </button>
        @endcan
    </x-page-header>

    <div class="flex flex-wrap gap-3 mb-4 items-center">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Nome, modello, seriale…" class="rounded-md border-gray-300 shadow-sm text-sm w-64" />
        <select wire:model.live="typeFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">Tutti i tipi</option>
            @foreach ($types as $t)
                <option value="{{ $t->value }}">{{ $t->label() }}</option>
            @endforeach
        </select>
        <select wire:model.live="rackFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="0">Tutti i rack</option>
            @foreach ($racks as $r)
                <option value="{{ $r->id }}">{{ $r->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">Tutti gli stati</option>
            @foreach ($statuses as $s)
                <option value="{{ $s->value }}">{{ $s->label() }}</option>
            @endforeach
        </select>
        <select wire:model.live="tagFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="0">Tutti i tag</option>
            @foreach ($allTags as $tag)
                <option value="{{ $tag->id }}">{{ $tag->name }}</option>
            @endforeach
        </select>
        @if ($search !== '' || $typeFilter !== '' || $rackFilter > 0 || $statusFilter !== '' || $tagFilter > 0)
            <button wire:click="clearFilters" type="button" class="text-xs text-gray-500 hover:text-gray-700 underline">Reset filtri</button>
        @endif
    </div>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendor / Modello</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rack</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Interfacce</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Azioni</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                @forelse ($equipment as $eq)
                    <tr wire:key="eq-{{ $eq->id }}">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <span class="inline-flex items-center gap-1.5">
                                <a href="{{ route('equipment.show', $eq) }}" wire:navigate class="text-indigo-700 hover:underline">{{ $eq->name }}</a>
                                @if ($eq->hidden_in_topology)
                                    <span title="Nascosto nella topologia" class="text-gray-400">
                                        <x-icon name="eye-slash" class="h-4 w-4" />
                                    </span>
                                @endif
                            </span>
                            @if ($eq->tags->isNotEmpty())
                                <div class="mt-1"><x-tag-chips :tags="$eq->tags" /></div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700">{{ $eq->type?->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $eq->vendor }} {{ $eq->model }}</td>
                        <td class="px-4 py-3 text-gray-600">
                            @if ($eq->rack)
                                {{ $eq->rack->name }}@if ($eq->mounted) · U{{ $eq->position_u_start }}–{{ $eq->position_u_start + $eq->position_u_height - 1 }}@endif
                                @if ($eq->rack->room)
                                    <span class="block text-xs text-gray-400">{{ $eq->rack->room->name }}</span>
                                @endif
                            @elseif ($eq->room)
                                <span class="italic text-gray-500">non rack-mounted</span>
                                <span class="block text-xs text-gray-400">{{ $eq->room->name }}</span>
                            @else
                                <span class="italic text-gray-400">non rack-mounted</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $eq->interfaces_count }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            @can('update', $eq)
                                <button wire:click="openEdit({{ $eq->id }})" class="text-indigo-600 hover:text-indigo-800"><x-icon name="pencil" class="h-4 w-4 inline" /></button>
                            @endcan
                            @can('delete', $eq)
                                <button wire:click="delete({{ $eq->id }})" wire:confirm="Eliminare {{ $eq->name }}?" class="text-red-600 hover:text-red-800"><x-icon name="trash" class="h-4 w-4 inline" /></button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Nessun dispositivo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $equipment->links() }}</div>

    @if ($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4 overflow-y-auto">
            <div class="bg-white rounded-md shadow-lg w-full max-w-2xl p-6 my-8">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? 'Modifica dispositivo' : 'Nuovo dispositivo' }}</h2>
                <form wire:submit="save" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome</label>
                            <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tipo</label>
                            <select wire:model="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                @foreach ($types as $t)
                                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                @endforeach
                            </select>
                            @error('type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vendor</label>
                            <input type="text" wire:model="vendor" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Modello</label>
                            <input type="text" wire:model="modelName" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Seriale</label>
                            <input type="text" wire:model="serial" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Stato</label>
                            <select wire:model="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                @foreach ($statuses as $s)
                                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Firmware</label>
                            <input type="text" wire:model="firmware" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            @error('firmware')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Asset tag</label>
                            <input type="text" wire:model="assetTag" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            @error('assetTag')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Mgmt IP</label>
                            <input type="text" wire:model="managementIp" placeholder="es. 192.168.1.1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            @error('managementIp')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="border-t pt-6 mt-2">
                        <div class="flex items-center gap-x-6 gap-y-5 flex-wrap">
                            <label class="inline-flex items-center gap-x-2 text-sm">
                                <input type="checkbox" wire:model.live="mounted" class="rounded border-gray-300 text-indigo-600" />
                                Rack-mounted
                            </label>
                            <label class="inline-flex items-center gap-x-2 text-sm">
                                <input type="checkbox" wire:model="locked" class="rounded border-gray-300 text-indigo-600" />
                                Bloccato (no drag&amp;drop)
                            </label>
                            <label class="inline-flex items-center gap-x-2 text-sm">
                                <input type="checkbox" wire:model="hiddenInTopology" class="rounded border-gray-300 text-indigo-600" />
                                Nascosto nella topologia
                            </label>
                            @if ($mounted)
                                <label class="inline-flex items-center gap-x-2 text-sm">
                                    <input type="checkbox" wire:model.live="onTop" class="rounded border-gray-300 text-indigo-600" />
                                    Sopra il rack (no U)
                                </label>
                            @endif
                        </div>

                        @if ($mounted)
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Rack</label>
                                    <select wire:model="rackId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        <option value="">—</option>
                                        @foreach ($racks as $r)
                                            <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->height_units }}U)</option>
                                        @endforeach
                                    </select>
                                    @error('rackId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Lato</label>
                                    <select wire:model="positionOrient" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        <option value="front">Anteriore</option>
                                        <option value="rear">Posteriore</option>
                                    </select>
                                    @error('positionOrient')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">U iniziale</label>
                                    <input type="number" min="1" wire:model="positionUStart"
                                           @disabled($onTop)
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100 disabled:text-gray-400" />
                                    @error('positionUStart')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Altezza (U)</label>
                                    <input type="number" min="1" wire:model="positionUHeight"
                                           @disabled($onTop)
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100 disabled:text-gray-400" />
                                    @error('positionUHeight')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        @endif

                        {{-- Locale: editable select when device is unracked; read-only
                             label derived from the rack when device is racked. --}}
                        <div class="mt-3">
                            @if (!$mounted)
                                <label class="block text-sm font-medium text-gray-700">Locale</label>
                                <select wire:model="roomId" class="mt-1 block w-full sm:w-1/2 rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">— Nessuno —</option>
                                    @foreach ($rooms as $r)
                                        <option value="{{ $r->id }}">{{ $r->name }} @if ($r->site) ({{ $r->site->name }}) @endif</option>
                                    @endforeach
                                </select>
                                @error('roomId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            @else
                                @php
                                    $derivedRoom = $rackId ? optional($racks->firstWhere('id', (int) $rackId))->room?->name : null;
                                @endphp
                                <p class="text-xs text-gray-500">
                                    Locale: <span class="font-medium text-gray-700">{{ $derivedRoom ?? '—' }}</span>
                                    <span class="text-gray-400">(derivato dal rack)</span>
                                </p>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Descrizione</label>
                        <textarea wire:model="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Icona (opzionale)</label>
                        @if ($iconUpload)
                            <div class="mt-1 flex items-center gap-3">
                                <img src="{{ $iconUpload->temporaryUrl() }}" alt="anteprima" class="h-16 w-16 object-contain rounded border border-gray-200 bg-gray-50" />
                                <button type="button" wire:click="$set('iconUpload', null)" class="text-xs text-red-600 hover:underline">Annulla upload</button>
                            </div>
                        @elseif ($existingIconPath)
                            <div class="mt-1 flex items-center gap-3">
                                <img src="/storage/{{ ltrim($existingIconPath, '/') }}" alt="icona attuale" class="h-16 w-16 object-contain rounded border border-gray-200 bg-gray-50" />
                                <button type="button" wire:click="removeIcon" wire:confirm="Rimuovere l'icona di questo dispositivo?" class="text-xs text-red-600 hover:underline">Rimuovi</button>
                            </div>
                        @endif
                        <input type="file" accept="image/*" wire:model="iconUpload" class="mt-1 block w-full text-sm" />
                        <p class="text-xs text-gray-500 mt-1">Sostituisce per questo dispositivo l'icona globale/cliente. Lascia vuoto per usare il default.</p>
                        @error('iconUpload')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        <div wire:loading wire:target="iconUpload" class="text-xs text-indigo-600 mt-1">Caricamento…</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tag</label>
                        <x-tag-selector :tags="$allTags" model="selectedTagIds" />
                        @error('selectedTagIds')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex justify-end gap-x-2 pt-2">
                        <button type="button" wire:click="$set('showForm', false)" class="px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Annulla</button>
                        <button type="submit" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Salva</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
