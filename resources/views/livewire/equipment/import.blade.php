<div>
    <x-page-header title="Import dispositivi" subtitle="Carica un CSV con i dispositivi da importare nel cliente attivo">
        <a href="{{ route('equipment.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-800">← Annulla</a>
        <a href="{{ route('export.equipment.template') }}" class="text-sm text-indigo-700 hover:underline">Scarica template CSV</a>
    </x-page-header>

    <ol class="flex gap-x-2 mb-6 text-xs max-w-3xl">
        @foreach (['Upload', 'Preview', 'Risultato'] as $i => $label)
            <li class="flex-1 px-3 py-2 rounded-md text-center {{ $step >= $i + 1 ? 'bg-indigo-100 text-indigo-700 font-medium' : 'bg-gray-100 text-gray-500' }}">
                {{ $i + 1 }}. {{ $label }}
            </li>
        @endforeach
    </ol>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-6 max-w-4xl">
        @if ($step === 1)
            <form wire:submit="toPreview" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">File CSV</label>
                    <input type="file" wire:model="file" accept=".csv,text/csv" class="mt-1 block w-full text-sm" />
                    @error('file')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <p class="text-xs text-gray-500 mt-1">Max 5 MB. Header attesi: name, type, vendor, model, serial, …</p>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Avanti →</button>
                </div>
            </form>
        @elseif ($step === 2)
            <p class="text-sm text-gray-600 mb-3">Anteprima delle prime {{ count($rowsPreview) }} righe:</p>
            <div class="overflow-x-auto border border-gray-200 rounded-md mb-4">
                <table class="min-w-full text-xs divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            @foreach ($headerPreview as $h)
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($rowsPreview as $r)
                            <tr>
                                @foreach ($r as $c)
                                    <td class="px-3 py-1.5 text-gray-700 whitespace-nowrap">{{ $c }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <label class="inline-flex items-center gap-x-2 text-sm">
                <input type="checkbox" wire:model="ignoreErrors" class="rounded border-gray-300 text-indigo-600" />
                Importa righe valide anche se altre falliscono (no rollback)
            </label>

            <div class="flex justify-between mt-6">
                <button wire:click="reset_" class="px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">← Cambia file</button>
                <button wire:click="runImport" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded-md hover:bg-emerald-700">Esegui import</button>
            </div>
        @else
            @if ($summary)
                <div class="space-y-3">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="bg-emerald-50 text-emerald-900 p-3 rounded">
                            <div class="text-xs uppercase">Creati</div>
                            <div class="text-2xl font-semibold">{{ $summary['created'] }}</div>
                        </div>
                        <div class="bg-amber-50 text-amber-900 p-3 rounded">
                            <div class="text-xs uppercase">Saltati</div>
                            <div class="text-2xl font-semibold">{{ $summary['skipped'] }}</div>
                        </div>
                        <div class="bg-red-50 text-red-900 p-3 rounded">
                            <div class="text-xs uppercase">Errori</div>
                            <div class="text-2xl font-semibold">{{ count($summary['errors']) }}</div>
                        </div>
                    </div>

                    @if (count($summary['errors']) > 0)
                        <div class="border border-red-200 rounded-md overflow-hidden">
                            <table class="min-w-full text-xs">
                                <thead class="bg-red-50 text-red-900">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Riga</th>
                                        <th class="px-3 py-2 text-left">Errori</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-red-100">
                                    @foreach ($summary['errors'] as $err)
                                        <tr>
                                            <td class="px-3 py-1.5 align-top font-mono">{{ $err['row'] }}</td>
                                            <td class="px-3 py-1.5">
                                                <ul class="list-disc list-inside">
                                                    @foreach ($err['messages'] as $m)
                                                        <li>{{ $m }}</li>
                                                    @endforeach
                                                </ul>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
            <div class="flex justify-end mt-6">
                <button wire:click="reset_" class="px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Nuovo import</button>
                <a href="{{ route('equipment.index') }}" wire:navigate class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700 ml-2">Vai ai dispositivi</a>
            </div>
        @endif
    </div>
</div>
