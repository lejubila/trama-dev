<div>
    @php
        $cancelUrl = $fromEquipmentId
            ? route('equipment.show', ['equipment' => $fromEquipmentId, 'tab' => 'connections'])
            : route('connections.index');
    @endphp
    <x-page-header title="Nuova connessione" subtitle="Step {{ $step }} di 3">
        <a href="{{ $cancelUrl }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-800">← Annulla</a>
    </x-page-header>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-6 max-w-3xl">
        <ol class="flex gap-x-2 mb-6 text-xs">
            @foreach (['Estremo A', 'Estremo B', 'Cavo'] as $i => $label)
                <li class="flex-1 px-3 py-2 rounded-md text-center {{ $step >= $i + 1 ? 'bg-indigo-100 text-indigo-700 font-medium' : 'bg-gray-100 text-gray-500' }}">
                    {{ $i + 1 }}. {{ $label }}
                </li>
            @endforeach
        </ol>

        @if ($step === 1)
            {{-- wire:key forces morphdom to NOT reuse the step-2 <select>
                 as if it were the same DOM node: structurally the two
                 selects are near-identical, and without distinct keys the
                 selected option from step 1 would bleed into step 2's
                 toInterfaceId via the deferred wire:model sync. --}}
            <div wire:key="step-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Interfaccia di partenza
                    @if ($fromEquipmentId)
                        <span class="text-xs text-gray-500 font-normal">— filtrato sul dispositivo selezionato</span>
                    @endif
                </label>
                <select wire:model="fromInterfaceId" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Seleziona…</option>
                    @foreach ($equipmentStep1 as $eq)
                        <optgroup label="{{ $eq->name }}">
                            @foreach ($eq->interfaces as $if)
                                <option value="{{ $if->id }}" @disabled(in_array($if->id, $busyIds, true))>{{ $if->name }} — {{ $if->type?->value }}@if (in_array($if->id, $busyIds, true)) (occupata) @endif</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('fromInterfaceId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @if ($fromEquipmentId)
                    <p class="text-xs text-gray-500 mt-1">
                        <a href="{{ route('connections.create') }}" wire:navigate class="text-indigo-600 hover:underline">Mostra tutti i dispositivi</a>
                    </p>
                @endif
            </div>
        @elseif ($step === 2)
            <div wire:key="step-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Interfaccia di destinazione</label>
                <select wire:model="toInterfaceId" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Seleziona…</option>
                    @foreach ($equipment as $eq)
                        <optgroup label="{{ $eq->name }}">
                            @foreach ($eq->interfaces as $if)
                                <option value="{{ $if->id }}" @disabled(in_array($if->id, $busyIds, true) || $if->id === $fromInterfaceId)>{{ $if->name }} — {{ $if->type?->value }}@if (in_array($if->id, $busyIds, true)) (occupata) @endif</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('toInterfaceId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        @else
            <form wire:submit="save" wire:key="step-3">
                {{-- Summary degli endpoint scelti agli step 1-2: rende visibile
                     l'eventuale errore di validazione su from/to che altrimenti
                     finirebbe in un error bag mai mostrato. --}}
                <div class="bg-gray-50 rounded p-3 mb-4 text-sm">
                    <div>
                        <span class="text-gray-500">Da:</span>
                        {{ $fromInterface?->equipment?->name ?? '—' }}
                        ·
                        <span class="font-mono">{{ $fromInterface?->name ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">A:</span>
                        {{ $toInterface?->equipment?->name ?? '—' }}
                        ·
                        <span class="font-mono">{{ $toInterface?->name ?? '—' }}</span>
                    </div>
                    @error('fromInterfaceId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    @error('toInterfaceId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipo cavo</label>
                        <select wire:model="cableType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="utp_cat6">utp_cat6</option>
                            <option value="utp_cat6a">utp_cat6a</option>
                            <option value="stp">stp</option>
                            <option value="fiber_om3">fiber_om3</option>
                            <option value="fiber_om4">fiber_om4</option>
                            <option value="dac">dac</option>
                        </select>
                        @error('cableType')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Lunghezza (m)</label>
                        <input type="number" step="0.1" wire:model="cableLengthM" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        @error('cableLengthM')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Etichetta</label>
                        <input type="text" wire:model="cableLabel" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        @error('cableLabel')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Colore</label>
                        <x-cable-color-picker :presets="$colorPresets" :value="$color" />
                        @error('color')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Posato il</label>
                        <input type="date" wire:model="establishedAt" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        @error('establishedAt')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Note</label>
                        <textarea wire:model="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                        @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tag</label>
                        <x-tag-selector :tags="$allTags" model="selectedTagIds" />
                        @error('selectedTagIds')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="flex justify-between items-center mt-6">
                    <button type="button" wire:click="back" class="px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Indietro</button>
                    <button type="submit" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded-md hover:bg-emerald-700">Crea connessione</button>
                </div>
            </form>
        @endif

        @if ($step < 3)
            <div class="flex justify-between items-center mt-6">
                <button type="button" @if ($step === 1) disabled @endif wire:click="back" class="px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md disabled:opacity-50">Indietro</button>
                <button type="button" wire:click="next" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Avanti →</button>
            </div>
        @endif
    </div>
</div>
