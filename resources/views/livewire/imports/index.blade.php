<div>
    <x-page-header title="Import" subtitle="Storico delle importazioni eseguite per il cliente attivo">
        <a href="{{ route('equipment.import') }}" wire:navigate class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
            <x-icon name="plus" class="h-4 w-4" /> Nuovo import
        </a>
    </x-page-header>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Utente</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stato</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Riepilogo</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase"></th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                @forelse ($imports as $imp)
                    <tr wire:key="imp-{{ $imp->id }}">
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $imp->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $imp->type }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $imp->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @php $color = match($imp->status) { 'completed' => 'emerald', 'failed' => 'red', default => 'amber' }; @endphp
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-700">{{ $imp->status }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">
                            {{ ($imp->summary['created'] ?? 0) }} creati ·
                            {{ count($imp->summary['errors'] ?? []) }} errori
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="toggle({{ $imp->id }})" class="text-xs text-indigo-700 hover:underline">
                                {{ $expandedId === $imp->id ? 'Chiudi' : 'Dettagli' }}
                            </button>
                        </td>
                    </tr>
                    @if ($expandedId === $imp->id)
                        <tr class="bg-gray-50">
                            <td colspan="6" class="px-4 py-3">
                                <pre class="text-xs font-mono whitespace-pre-wrap max-h-96 overflow-auto">{{ json_encode($imp->summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Nessun import.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $imports->links() }}</div>
</div>
