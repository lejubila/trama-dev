<div>
    <x-page-header title="API Tokens" subtitle="Token di accesso personali per l'API REST" />

    @if ($newPlainToken)
        <div class="bg-emerald-50 border border-emerald-200 rounded-md p-4 mb-6">
            <h3 class="font-semibold text-emerald-900">Token creato</h3>
            <p class="text-sm text-emerald-800 mt-1">Copialo ora — non sarà più mostrato.</p>
            <code class="block mt-2 p-2 rounded bg-emerald-100 text-emerald-950 font-mono text-xs break-all">{{ $newPlainToken }}</code>
            <button wire:click="$set('newPlainToken', null)" class="text-xs text-emerald-700 hover:text-emerald-900 underline mt-2">Ho copiato</button>
        </div>
    @endif

    <form wire:submit="create" class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4 mb-6 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">Nome token</label>
            <input type="text" wire:model="name" placeholder="es. Postman su laptop" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Abilità</label>
            <div class="mt-1 flex gap-x-3 text-sm">
                <label class="inline-flex items-center gap-x-1.5">
                    <input type="checkbox" wire:model="abilities" value="read" class="rounded border-gray-300 text-indigo-600" />
                    read
                </label>
                <label class="inline-flex items-center gap-x-1.5">
                    <input type="checkbox" wire:model="abilities" value="write" class="rounded border-gray-300 text-indigo-600" />
                    write
                </label>
            </div>
        </div>
        <div class="md:col-span-3 flex justify-end">
            <button type="submit" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Crea token</button>
        </div>
    </form>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Abilità</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ultimo uso</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Creato</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Azioni</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                @forelse ($tokens as $t)
                    <tr wire:key="tok-{{ $t->id }}">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $t->name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ implode(', ', (array) $t->abilities) }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $t->last_used_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $t->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="revoke({{ $t->id }})" wire:confirm="Revocare il token {{ $t->name }}?" class="text-red-600 hover:text-red-800 text-xs">Revoca</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Nessun token.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
