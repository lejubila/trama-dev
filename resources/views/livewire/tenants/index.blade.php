<div>
    <x-page-header title="Clienti" subtitle="I clienti (workspace) ai quali sei associato">
        @can('create', App\Models\Tenant::class)
            <button wire:click="openCreate" class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                <x-icon name="plus" class="h-4 w-4" /> Nuovo cliente
            </button>
        @endcan
    </x-page-header>

    <div class="bg-white dark:bg-slate-800 shadow ring-1 ring-black ring-opacity-5 dark:ring-slate-600 rounded-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
            <thead class="bg-gray-50 dark:bg-slate-700/50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Nome</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Slug</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Mio ruolo</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Stato</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Azioni</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700 text-sm">
                @forelse ($tenants as $tenant)
                    @php $role = $tenant->getRelationValue('pivot')?->getAttribute('role'); @endphp
                    <tr wire:key="t-{{ $tenant->id }}">
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-slate-100">
                            <a href="{{ route('tenants.manage', $tenant) }}" wire:navigate class="text-indigo-700 dark:text-indigo-300 hover:underline">{{ $tenant->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-slate-400 font-mono">{{ $tenant->slug }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-200">{{ $role }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-slate-400">
                            @if ((int) $currentTenantId === (int) $tenant->id)
                                <span class="text-emerald-600 dark:text-emerald-400">● attivo</span>
                            @else
                                <button wire:click="switchTo({{ $tenant->id }})" class="text-xs text-indigo-700 dark:text-indigo-300 hover:underline">Attiva</button>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('tenants.manage', $tenant) }}" wire:navigate class="text-xs text-indigo-700 dark:text-indigo-300 hover:underline">Gestisci →</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-slate-400">
                            <p class="mb-3">Non sei ancora associato a nessun cliente.</p>
                            @can('create', App\Models\Tenant::class)
                                <button wire:click="openCreate" class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                                    <x-icon name="plus" class="h-4 w-4" /> Crea il tuo primo cliente
                                </button>
                            @endcan
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white dark:bg-slate-800 rounded-md shadow-lg w-full max-w-md p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100 mb-4">Nuovo cliente</h2>
                <form wire:submit="save" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Nome</label>
                        <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                        @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Slug (opzionale)</label>
                        <input type="text" wire:model="slug" placeholder="auto-generato dal nome" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm font-mono" />
                        @error('slug')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Dominio (opzionale)</label>
                        <input type="text" wire:model="domain" placeholder="es. acme.example.com" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                        @error('domain')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <p class="text-xs text-gray-500 dark:text-slate-400">Diventerai automaticamente <strong>admin</strong> del nuovo cliente.</p>
                    <div class="flex justify-end gap-x-2 pt-2">
                        <button type="button" wire:click="$set('showForm', false)" class="px-3 py-2 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-md">Annulla</button>
                        <button type="submit" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Crea</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
