<div>
    <x-page-header title="Nuova connessione" subtitle="Step {{ $step }} di 3">
        <a href="{{ route('connections.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-800">← Annulla</a>
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
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Interfaccia di partenza</label>
                <select wire:model="fromInterfaceId" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Seleziona…</option>
                    @foreach ($equipment as $eq)
                        <optgroup label="{{ $eq->name }}">
                            @foreach ($eq->interfaces as $if)
                                <option value="{{ $if->id }}" @disabled(in_array($if->id, $busyIds, true))>{{ $if->name }} — {{ $if->type?->value }}@if (in_array($if->id, $busyIds, true)) (occupata) @endif</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('fromInterfaceId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        @elseif ($step === 2)
            <div>
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
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo cavo</label>
                    <select wire:model="cableType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option>utp_cat6</option>
                        <option>utp_cat6a</option>
                        <option>stp</option>
                        <option>fiber_om3</option>
                        <option>fiber_om4</option>
                        <option>dac</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Lunghezza (m)</label>
                    <input type="number" step="0.1" wire:model="cableLengthM" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Etichetta</label>
                    <input type="text" wire:model="cableLabel" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Colore</label>
                    <input type="text" wire:model="color" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Posato il</label>
                    <input type="date" wire:model="establishedAt" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Note</label>
                    <textarea wire:model="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                </div>
            </div>
        @endif

        <div class="flex justify-between items-center mt-6">
            <button type="button" @if ($step === 1) disabled @endif wire:click="back" class="px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md disabled:opacity-50">Indietro</button>
            @if ($step < 3)
                <button type="button" wire:click="next" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Avanti →</button>
            @else
                <button type="button" wire:click="save" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded-md hover:bg-emerald-700">Crea connessione</button>
            @endif
        </div>
    </div>
</div>
