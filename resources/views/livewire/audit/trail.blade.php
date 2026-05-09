<div>
    <x-page-header title="Audit log" subtitle="Storico delle modifiche su dispositivi, interfacce e connessioni" />

    <div class="flex flex-wrap gap-3 mb-4">
        <select wire:model.live="modelFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">Tutti i modelli</option>
            @foreach ($modelTypes as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="eventFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">Tutti gli eventi</option>
            @foreach ($events as $e)
                <option value="{{ $e }}">{{ $e }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Utente</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Modello</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Evento</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Diff</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                @forelse ($audits as $a)
                    <tr wire:key="audit-{{ $a->id }}">
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $a->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ optional($a->user)->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ class_basename($a->auditable_type) }}#{{ $a->auditable_id }}</td>
                        <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700">{{ $a->event }}</span></td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-600">
                            <details>
                                <summary class="cursor-pointer">Espandi</summary>
                                <pre class="mt-1 max-h-48 overflow-auto bg-gray-50 p-2 rounded">{{ json_encode(['old' => $a->old_values, 'new' => $a->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Nessun audit.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $audits->links() }}</div>
</div>
