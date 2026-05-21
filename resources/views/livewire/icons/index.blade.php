<div>
    <x-page-header title="Icone" subtitle="Libreria icone per rack e tipologie di dispositivi. Override per cliente sopra la libreria globale.">
    </x-page-header>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipologia</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Globale</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Override per cliente {{ $tenant ? '— '.$tenant->name : '' }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
                @foreach ($kinds as $kind)
                    @php
                        $k = $kind['key'];
                        $globalPath = $globalByKind[$k] ?? null;
                        $tenantPath = $tenantByKind[$k] ?? null;
                    @endphp
                    <tr wire:key="kind-{{ $k }}">
                        <td class="px-4 py-3 font-medium text-gray-900 align-top">{{ $kind['label'] }}</td>

                        <td class="px-4 py-3 align-top">
                            <div class="flex items-start gap-3">
                                <div class="h-16 w-16 shrink-0 rounded border border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden">
                                    @if (! empty($globalUpload[$k]))
                                        <img src="{{ $globalUpload[$k]->temporaryUrl() }}" alt="upload" class="max-h-full max-w-full object-contain" />
                                    @elseif ($globalPath)
                                        <img src="/storage/{{ ltrim($globalPath, '/') }}" alt="globale" class="max-h-full max-w-full object-contain" />
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </div>
                                <div class="flex-1 text-xs">
                                    @can('manageGlobal', App\Models\DeviceIcon::class)
                                        <input type="file" accept="image/*" wire:model="globalUpload.{{ $k }}" class="block w-full text-xs" />
                                        @error("globalUpload.$k")<p class="text-red-600 mt-1">{{ $message }}</p>@enderror
                                        <div wire:loading wire:target="globalUpload.{{ $k }}" class="text-indigo-600 mt-1">Caricamento…</div>
                                        <div class="flex gap-2 mt-2">
                                            @if (! empty($globalUpload[$k]))
                                                <button type="button" wire:click="saveGlobal('{{ $k }}')" class="px-2 py-1 text-white bg-indigo-600 rounded hover:bg-indigo-700">Salva</button>
                                                <button type="button" wire:click="$set('globalUpload.{{ $k }}', null)" class="text-gray-600 hover:underline">Annulla</button>
                                            @elseif ($globalPath)
                                                <button type="button" wire:click="removeGlobal('{{ $k }}')" wire:confirm="Rimuovere l'icona globale?" class="text-red-600 hover:underline">Rimuovi</button>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-gray-400">Solo gli amministratori possono modificare le icone globali.</span>
                                    @endcan
                                </div>
                            </div>
                        </td>

                        <td class="px-4 py-3 align-top">
                            @if (! $tenant)
                                <span class="text-xs italic text-gray-500">Nessun cliente attivo.</span>
                            @else
                                <div class="flex items-start gap-3">
                                    <div class="h-16 w-16 shrink-0 rounded border border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden">
                                        @if (! empty($tenantUpload[$k]))
                                            <img src="{{ $tenantUpload[$k]->temporaryUrl() }}" alt="upload" class="max-h-full max-w-full object-contain" />
                                        @elseif ($tenantPath)
                                            <img src="/storage/{{ ltrim($tenantPath, '/') }}" alt="override" class="max-h-full max-w-full object-contain" />
                                        @else
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </div>
                                    <div class="flex-1 text-xs">
                                        <input type="file" accept="image/*" wire:model="tenantUpload.{{ $k }}" class="block w-full text-xs" />
                                        @error("tenantUpload.$k")<p class="text-red-600 mt-1">{{ $message }}</p>@enderror
                                        <div wire:loading wire:target="tenantUpload.{{ $k }}" class="text-indigo-600 mt-1">Caricamento…</div>
                                        <div class="flex gap-2 mt-2">
                                            @if (! empty($tenantUpload[$k]))
                                                <button type="button" wire:click="saveTenant('{{ $k }}')" class="px-2 py-1 text-white bg-indigo-600 rounded hover:bg-indigo-700">Salva</button>
                                                <button type="button" wire:click="$set('tenantUpload.{{ $k }}', null)" class="text-gray-600 hover:underline">Annulla</button>
                                            @elseif ($tenantPath)
                                                <button type="button" wire:click="removeTenant('{{ $k }}')" wire:confirm="Rimuovere l'override?" class="text-red-600 hover:underline">Rimuovi</button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500 mt-3">
        L'icona usata viene risolta come: <strong>override sul record</strong> (sul singolo rack o dispositivo) → <strong>override per cliente</strong> (questa tabella) → <strong>libreria globale</strong> → forma di default.
    </p>
</div>
