<div>
    <x-page-header :title="$document ? 'Modifica documento' : 'Nuovo documento'"
                   subtitle="Componi la documentazione PDF del cliente">
        <a href="{{ route('documents.index') }}" wire:navigate
           class="text-sm text-gray-600 hover:text-gray-800">← Torna alla lista</a>
    </x-page-header>

    <form wire:submit="save" class="space-y-6">

        {{-- Header --}}
        <div class="bg-white shadow ring-1 ring-black/5 rounded-md p-4 space-y-3">
            <h3 class="font-semibold text-gray-700">Informazioni documento</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Titolo</label>
                    <input type="text" wire:model="title"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    @error('title')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Data</label>
                    <input type="date" wire:model="documentDate"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    @error('documentDate')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Descrizione</label>
                <textarea wire:model="description" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                @error('description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="flex items-center gap-6 text-sm pt-1">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model="includeCover" class="rounded border-gray-300 text-indigo-600">
                    Includi copertina
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model="includeToc" class="rounded border-gray-300 text-indigo-600">
                    Includi indice
                </label>
            </div>
            <p class="mt-3 text-xs text-gray-500">
                Il documento è organizzato in modo gerarchico: ogni Sede contiene i propri Locali, ogni Locale i propri Rack (con elevation front/rear e foto) e i dispositivi non in rack. Un elemento appare solo se anche tutti i suoi contenitori (sede, locale, eventuale rack) sono selezionati.
            </p>
        </div>

        {{-- Sezione: Contenuti (albero gerarchico Sedi → Locali → Rack → Dispositivi) --}}
        <div class="bg-white shadow ring-1 ring-black/5 rounded-md p-4 space-y-4">
            <h3 class="font-semibold text-gray-700">Contenuti</h3>

            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                <span class="text-gray-500">Livelli da includere:</span>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model.live="sitesEnabled" class="rounded border-gray-300 text-indigo-600"> Sedi
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model.live="roomsEnabled" class="rounded border-gray-300 text-indigo-600"> Locali
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model.live="racksEnabled" class="rounded border-gray-300 text-indigo-600"> Rack
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model.live="equipmentEnabled" class="rounded border-gray-300 text-indigo-600"> Dispositivi
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Descrizione sezione Sedi</label>
                    <textarea wire:model="sitesDescription" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Descrizione sezione Locali</label>
                    <textarea wire:model="roomsDescription" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                </div>
            </div>

            <p class="text-xs text-gray-500">
                Spunta gli elementi da includere ed espandi una sede/locale/rack selezionato per scegliere
                i figli. Usa le frecce ▲▼ per ordinare gli elementi selezionati: l'ordine scelto è quello
                con cui compariranno nel PDF. Un elemento appare solo se anche tutti i suoi contenitori sono
                selezionati e il relativo livello è incluso.
            </p>

            <div class="border border-gray-200 rounded p-2 max-h-[28rem] overflow-y-auto" style="display:flex; flex-direction:column; gap:8px;">
                @forelse ($tree as $siteNode)
                    @php $site = $siteNode->model; @endphp
                    <div wire:key="site-{{ $site->id }}" style="order: {{ $loop->index }}">
                        <div class="flex items-center gap-2 text-base py-1">
                            <input type="checkbox" value="{{ $site->id }}" wire:model.live="sitesIds"
                                   class="rounded border-gray-300 text-indigo-600">
                            @if ($siteNode->selected)
                                @include('livewire.documents._reorder', ['id' => $site->id, 'method' => 'moveSite', 'canUp' => $siteNode->canUp, 'canDown' => $siteNode->canDown])
                            @endif
                            <span class="font-medium">{{ $site->name }}</span>
                        </div>

                        @if ($siteNode->selected)
                            <div class="ml-2 mt-1 border-l border-gray-200 pl-4" style="display:flex; flex-direction:column; gap:8px;">
                                @forelse ($siteNode->rooms as $roomNode)
                                    @php $room = $roomNode->model; @endphp
                                    <div wire:key="room-{{ $room->id }}" style="order: {{ $loop->index }}">
                                        <div class="flex items-center gap-2 text-base py-1">
                                            <input type="checkbox" value="{{ $room->id }}" wire:model.live="roomsIds"
                                                   class="rounded border-gray-300 text-indigo-600">
                                            @if ($roomNode->selected)
                                                @include('livewire.documents._reorder', ['id' => $room->id, 'method' => 'moveRoom', 'canUp' => $roomNode->canUp, 'canDown' => $roomNode->canDown])
                                            @endif
                                            <span>{{ $room->name }}</span>
                                        </div>

                                        @if ($roomNode->selected)
                                            <div class="ml-2 mt-1 border-l border-gray-200 pl-4" style="display:flex; flex-direction:column; gap:8px;">
                                                <div style="display:flex; flex-direction:column; gap:8px;">
                                                @foreach ($roomNode->racks as $rackNode)
                                                    @php $rack = $rackNode->model; @endphp
                                                    <div wire:key="rack-{{ $rack->id }}" style="order: {{ $loop->index }}">
                                                        <div class="flex items-center gap-2 text-base py-1">
                                                            <input type="checkbox" value="{{ $rack->id }}" wire:model.live="racksIds"
                                                                   class="rounded border-gray-300 text-indigo-600">
                                                            @if ($rackNode->selected)
                                                                @include('livewire.documents._reorder', ['id' => $rack->id, 'method' => 'moveRack', 'canUp' => $rackNode->canUp, 'canDown' => $rackNode->canDown])
                                                            @endif
                                                            <span class="text-gray-700">Rack {{ $rack->name }}</span>
                                                        </div>

                                                        @if ($rackNode->selected && $rackNode->equipment->isNotEmpty())
                                                            <div class="ml-2 mt-1 border-l border-gray-200 pl-4" style="display:flex; flex-direction:column; gap:8px;">
                                                                @foreach ($rackNode->equipment as $eqNode)
                                                                    @php $eq = $eqNode->model; @endphp
                                                                    <div class="flex items-center gap-2 text-base py-1" wire:key="eq-{{ $eq->id }}" style="order: {{ $loop->index }}">
                                                                        <input type="checkbox" value="{{ $eq->id }}" wire:model.live="equipmentIds"
                                                                               class="rounded border-gray-300 text-indigo-600">
                                                                        @if ($eqNode->selected)
                                                                            @include('livewire.documents._reorder', ['id' => $eq->id, 'method' => 'moveEquipment', 'canUp' => $eqNode->canUp, 'canDown' => $eqNode->canDown])
                                                                        @endif
                                                                        <span class="text-gray-600">{{ $eq->name }}</span>
                                                                        @if ($eqNode->selected)
                                                                            <label class="inline-flex items-center gap-1 text-sm text-gray-500 ml-2">
                                                                                <input type="checkbox" wire:click="toggleEquipmentPorts({{ $eq->id }})"
                                                                                       @checked(! in_array($eq->id, $equipmentPortsExcluded))
                                                                                       class="rounded border-gray-300 text-indigo-600">
                                                                                porte
                                                                            </label>
                                                                        @endif
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                                </div>

                                                @if ($roomNode->unracked->isNotEmpty())
                                                    <div style="display:flex; flex-direction:column; gap:8px;">
                                                    <div class="text-sm text-gray-400 pt-1" style="order:-1">Dispositivi non in rack</div>
                                                    @foreach ($roomNode->unracked as $eqNode)
                                                        @php $eq = $eqNode->model; @endphp
                                                        <div class="flex items-center gap-2 text-base py-1" wire:key="eq-{{ $eq->id }}" style="order: {{ $loop->index }}">
                                                            <input type="checkbox" value="{{ $eq->id }}" wire:model.live="equipmentIds"
                                                                   class="rounded border-gray-300 text-indigo-600">
                                                            @if ($eqNode->selected)
                                                                @include('livewire.documents._reorder', ['id' => $eq->id, 'method' => 'moveEquipment', 'canUp' => $eqNode->canUp, 'canDown' => $eqNode->canDown])
                                                            @endif
                                                            <span class="text-gray-600">{{ $eq->name }}</span>
                                                            @if ($eqNode->selected)
                                                                <label class="inline-flex items-center gap-1 text-sm text-gray-500 ml-2">
                                                                    <input type="checkbox" wire:click="toggleEquipmentPorts({{ $eq->id }})"
                                                                           @checked(! in_array($eq->id, $equipmentPortsExcluded))
                                                                           class="rounded border-gray-300 text-indigo-600">
                                                                    porte
                                                                </label>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-xs text-gray-400 italic">Nessun locale in questa sede.</p>
                                @endforelse
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500 italic">Nessuna sede disponibile.</p>
                @endforelse
            </div>
        </div>

        {{-- Sezione: Topologie (snapshot) — selezione + orientamento --}}
        <div class="bg-white shadow ring-1 ring-black/5 rounded-md">
            <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                <label class="inline-flex items-center gap-3">
                    <input type="checkbox" wire:model.live="topologiesEnabled"
                           class="rounded border-gray-300 text-indigo-600">
                    <span class="font-semibold text-gray-700">Topologie</span>
                </label>
                <span class="text-xs text-gray-500">
                    Selezionati: {{ count($topologiesItems) }} / {{ $snapshotCount }}
                </span>
            </div>
            <div class="p-4 space-y-3 @if (!$topologiesEnabled) opacity-50 pointer-events-none @endif">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Descrizione di sezione</label>
                    <textarea wire:model="topologiesDescription" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                </div>
                @if ($snapshotCount === 0)
                    <p class="text-sm text-gray-500 italic">Nessuno snapshot disponibile. Salvane uno dalla topologia.</p>
                @else
                    <div class="space-y-2 max-h-80 overflow-y-auto border border-gray-200 rounded p-2">
                        <div style="display:flex; flex-direction:column; gap:8px;">
                        @foreach ($selectedSnaps as $snap)
                            <div class="flex items-center gap-3 py-1 text-sm bg-indigo-50 rounded px-2" wire:key="snap-sel-{{ $snap->id }}" style="order: {{ $loop->index }}">
                                @include('livewire.documents._reorder', ['id' => $snap->id, 'method' => 'moveTopology', 'canUp' => $loop->first === false, 'canDown' => $loop->last === false])
                                <input type="checkbox" checked
                                       wire:click="toggleSnapshot({{ $snap->id }})"
                                       class="rounded border-gray-300 text-indigo-600">
                                <div class="flex-1">
                                    <div class="font-medium">{{ $snap->title }}</div>
                                    <div class="text-xs text-gray-500">{{ $snap->snapshot_date->format('d/m/Y') }}</div>
                                </div>
                                <select wire:change="setSnapshotOrientation({{ $snap->id }}, $event.target.value)"
                                        class="rounded border-gray-300 text-xs">
                                    <option value="portrait" @selected($topologiesItems[$snap->id] === 'portrait')>Portrait</option>
                                    <option value="landscape" @selected($topologiesItems[$snap->id] === 'landscape')>Landscape</option>
                                </select>
                            </div>
                        @endforeach
                        </div>
                        @foreach ($restSnaps as $snap)
                            <div class="flex items-center gap-3 py-1 text-sm rounded px-2" wire:key="snap-rest-{{ $snap->id }}">
                                <input type="checkbox"
                                       wire:click="toggleSnapshot({{ $snap->id }})"
                                       class="rounded border-gray-300 text-indigo-600">
                                <div class="flex-1">
                                    <div class="font-medium">{{ $snap->title }}</div>
                                    <div class="text-xs text-gray-500">{{ $snap->snapshot_date->format('d/m/Y') }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="px-4 py-2 rounded bg-indigo-600 text-white font-medium hover:bg-indigo-700 disabled:opacity-50">
                Salva e genera PDF
            </button>
            <span wire:loading wire:target="save" class="text-sm text-gray-500">Generazione in corso…</span>
            <a href="{{ route('documents.index') }}" wire:navigate
               class="text-sm text-gray-600 hover:text-gray-800">Annulla</a>
        </div>
    </form>
</div>
