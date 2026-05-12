<div>
    <x-page-header :title="$tenant->name" :subtitle="'Slug: '.$tenant->slug">
        <a href="{{ route('tenants.index') }}" wire:navigate class="text-sm text-gray-600 dark:text-slate-400 hover:text-gray-800">← Tutti i clienti</a>
    </x-page-header>

    <div class="border-b border-gray-200 dark:border-slate-700 mb-6">
        <nav class="flex gap-x-4 text-sm">
            @foreach (['general' => 'Generale', 'members' => 'Membri'] as $key => $label)
                <button
                    wire:click="setTab('{{ $key }}')"
                    class="py-2 px-1 border-b-2 {{ $activeTab === $key ? 'border-indigo-600 text-indigo-700 dark:text-indigo-300 font-medium' : 'border-transparent text-gray-500 dark:text-slate-400 hover:text-gray-700' }}"
                >{{ $label }}</button>
            @endforeach
        </nav>
    </div>

    @if ($activeTab === 'general')
        <div class="bg-white dark:bg-slate-800 shadow ring-1 ring-black ring-opacity-5 dark:ring-slate-600 rounded-md p-6 max-w-xl">
            @can('update', $tenant)
                <form wire:submit="saveGeneral" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Nome</label>
                        <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                        @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Slug</label>
                        <input type="text" wire:model="slug" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm font-mono" />
                        @error('slug')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Dominio</label>
                        <input type="text" wire:model="domain" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                        @error('domain')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex justify-end pt-2">
                        <button type="submit" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Salva</button>
                    </div>
                </form>
            @else
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-gray-500 dark:text-slate-400 inline">Nome:</dt> {{ $tenant->name }}</div>
                    <div><dt class="text-gray-500 dark:text-slate-400 inline">Slug:</dt> {{ $tenant->slug }}</div>
                    <div><dt class="text-gray-500 dark:text-slate-400 inline">Dominio:</dt> {{ $tenant->domain ?? '—' }}</div>
                </dl>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-3">Solo gli admin possono modificare i dati del cliente.</p>
            @endcan
        </div>

        @can('delete', $tenant)
            <div class="bg-red-50 dark:bg-red-900/20 ring-1 ring-red-200 dark:ring-red-800/40 rounded-md p-6 max-w-xl mt-6">
                <h3 class="text-base font-semibold text-red-900 dark:text-red-200">Zona pericolosa</h3>
                <p class="text-sm text-red-800 dark:text-red-300 mt-1">L'eliminazione del cliente rimuove <strong>tutti</strong> i dati associati (sedi, rack, dispositivi, interfacce, connessioni, audit log).</p>
                <button
                    wire:click="deleteTenant"
                    wire:confirm="Eliminare definitivamente {{ $tenant->name }} e tutti i dati associati?"
                    class="mt-3 px-3 py-2 text-sm text-white bg-red-600 rounded-md hover:bg-red-700"
                >Elimina cliente</button>
            </div>
        @endcan
    @endif

    @if ($activeTab === 'members')
        @can('update', $tenant)
            <form wire:submit="addMember" class="bg-white dark:bg-slate-800 shadow ring-1 ring-black ring-opacity-5 dark:ring-slate-600 rounded-md p-4 mb-6 grid grid-cols-1 md:grid-cols-[1fr_10rem_auto] gap-3 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Utente</label>
                    @if ($candidates->isEmpty())
                        <p class="mt-1 text-sm text-gray-500 dark:text-slate-400 italic">Tutti gli utenti registrati sono già membri.</p>
                    @else
                        <select wire:model="newUserId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm">
                            <option value="">Seleziona…</option>
                            @foreach ($candidates as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    @endif
                    @error('newUserId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Ruolo</label>
                    <select wire:model="newRole" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm">
                        <option value="admin">Admin</option>
                        <option value="tecnico">Tecnico</option>
                        <option value="cliente">Cliente</option>
                    </select>
                </div>
                <button type="submit" @disabled($candidates->isEmpty()) class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">Aggiungi</button>
            </form>
        @endcan

        <div class="bg-white dark:bg-slate-800 shadow ring-1 ring-black ring-opacity-5 dark:ring-slate-600 rounded-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                <thead class="bg-gray-50 dark:bg-slate-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Nome</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Ruolo</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Azioni</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700 text-sm">
                    @forelse ($members as $m)
                        @php $role = $m->getRelationValue('pivot')?->getAttribute('role') ?? '?'; @endphp
                        <tr wire:key="m-{{ $m->id }}">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-slate-100">{{ $m->name }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-slate-400">{{ $m->email }}</td>
                            <td class="px-4 py-3">
                                @can('update', $tenant)
                                    <select
                                        wire:change="changeRole({{ $m->id }}, $event.target.value)"
                                        class="rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-xs"
                                    >
                                        <option value="admin" @selected($role === 'admin')>admin</option>
                                        <option value="tecnico" @selected($role === 'tecnico')>tecnico</option>
                                        <option value="cliente" @selected($role === 'cliente')>cliente</option>
                                    </select>
                                @else
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-200">{{ $role }}</span>
                                @endcan
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('update', $tenant)
                                    <button
                                        wire:click="removeMember({{ $m->id }})"
                                        wire:confirm="Rimuovere {{ $m->name }} dal cliente?"
                                        class="text-xs text-red-600 hover:text-red-800"
                                    >Rimuovi</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-slate-400">Nessun membro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
