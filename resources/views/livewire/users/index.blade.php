<div>
    <x-page-header title="Utenti" subtitle="Gestione globale degli utenti">
        @can('create', App\Models\User::class)
            <button wire:click="openCreate" class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                <x-icon name="plus" class="h-4 w-4" /> Nuovo utente
            </button>
        @endcan
    </x-page-header>

    @if ($showForm)
        <div class="bg-white dark:bg-slate-800 shadow ring-1 ring-black ring-opacity-5 dark:ring-slate-600 rounded-md p-6 max-w-xl mb-6">
            <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100 mb-4">
                {{ $editingId === null ? 'Nuovo utente' : 'Modifica utente' }}
            </h3>
            <form wire:submit="save" class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Nome</label>
                    <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Email</label>
                    <input type="email" wire:model="email" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                    @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">
                        Password {{ $editingId !== null ? '(lascia vuoto per non modificare)' : '' }}
                    </label>
                    <input type="password" wire:model="password" autocomplete="new-password" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                    @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Ruolo</label>
                    <select wire:model.live="role" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm">
                        <option value="admin">Amministratore</option>
                        <option value="tecnico">Tecnico</option>
                        <option value="cliente">Cliente</option>
                    </select>
                    @error('role')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                @if ($role === 'cliente')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Clienti assegnati</label>
                        <p class="text-xs text-gray-500 dark:text-slate-400 mb-2">Il cliente vedrà solo i tenant selezionati. Admin e tecnici li vedono tutti senza assegnazione.</p>
                        <div class="max-h-48 overflow-y-auto rounded-md border border-gray-200 dark:border-slate-600 p-2 space-y-1">
                            @forelse ($tenants as $t)
                                <label class="flex items-center gap-x-2 text-sm text-gray-700 dark:text-slate-200">
                                    <input type="checkbox" value="{{ $t->id }}" wire:model="assignedTenantIds" class="rounded border-gray-300 text-indigo-600" />
                                    {{ $t->name }}
                                </label>
                            @empty
                                <p class="text-sm text-gray-500 dark:text-slate-400 italic">Nessun cliente disponibile.</p>
                            @endforelse
                        </div>
                        @error('assignedTenantIds')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                @endif

                <div class="flex justify-end gap-x-2 pt-2">
                    <button type="button" wire:click="cancel" class="px-3 py-2 text-sm text-gray-700 dark:text-slate-300 bg-gray-100 dark:bg-slate-700 rounded-md hover:bg-gray-200">Annulla</button>
                    <button type="submit" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Salva</button>
                </div>
            </form>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-800 shadow ring-1 ring-black ring-opacity-5 dark:ring-slate-600 rounded-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
            <thead class="bg-gray-50 dark:bg-slate-700/50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Nome</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Email</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Ruolo</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Clienti assegnati</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Azioni</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700 text-sm">
                @forelse ($users as $u)
                    <tr wire:key="u-{{ $u->id }}">
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-slate-100">{{ $u->name }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-slate-400">{{ $u->email }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-200">{{ $u->role->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-slate-400">
                            @if ($u->role === App\Enums\UserRole::Cliente)
                                {{ $u->tenants->pluck('name')->join(', ') ?: '—' }}
                            @else
                                <span class="text-gray-400 dark:text-slate-500 italic">tutti</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right space-x-3">
                            @can('update', $u)
                                <button wire:click="openEdit({{ $u->id }})" class="text-xs text-indigo-700 dark:text-indigo-300 hover:underline">Modifica</button>
                            @endcan
                            @can('delete', $u)
                                <button
                                    wire:click="delete({{ $u->id }})"
                                    wire:confirm="Eliminare definitivamente {{ $u->name }}?"
                                    class="text-xs text-red-600 hover:text-red-800"
                                >Elimina</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-slate-400">Nessun utente.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
