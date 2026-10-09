<div>
    <x-page-header :title="$tenant->name" :subtitle="__('tenants.slug_subtitle', ['slug' => $tenant->slug])">
        <a href="{{ route('tenants.index') }}" wire:navigate class="text-sm text-gray-600 dark:text-slate-400 hover:text-gray-800">{{ __('tenants.all_customers_back') }}</a>
    </x-page-header>

    <div class="border-b border-gray-200 dark:border-slate-700 mb-6">
        <nav class="flex gap-x-4 text-sm">
            @foreach (['general' => __('common.general'), 'members' => __('tenants.tab_members')] as $key => $label)
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
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('common.name') }}</label>
                        <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                        @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('common.slug') }}</label>
                        <input type="text" wire:model="slug" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm font-mono" />
                        @error('slug')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('common.domain') }}</label>
                        <input type="text" wire:model="domain" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                        @error('domain')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex justify-end pt-2">
                        <button type="submit" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">{{ __('common.save') }}</button>
                    </div>
                </form>
            @else
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-gray-500 dark:text-slate-400 inline">{{ __('common.name') }}:</dt> {{ $tenant->name }}</div>
                    <div><dt class="text-gray-500 dark:text-slate-400 inline">{{ __('common.slug') }}:</dt> {{ $tenant->slug }}</div>
                    <div><dt class="text-gray-500 dark:text-slate-400 inline">{{ __('common.domain') }}:</dt> {{ $tenant->domain ?? '—' }}</div>
                </dl>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-3">{{ __('tenants.only_admins_edit') }}</p>
            @endcan
        </div>

        @can('export', $tenant)
            <div class="bg-white dark:bg-slate-800 shadow ring-1 ring-black ring-opacity-5 dark:ring-slate-600 rounded-md p-6 max-w-xl mt-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">{{ __('tenants.export_heading') }}</h3>
                <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">{!! __('tenants.export_help_html') !!}</p>
                <a href="{{ route('tenants.export', $tenant) }}" class="mt-3 inline-flex px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">{{ __('tenants.export_button') }}</a>
            </div>
        @endcan

        @can('delete', $tenant)
            <div class="bg-red-50 dark:bg-red-900/20 ring-1 ring-red-200 dark:ring-red-800/40 rounded-md p-6 max-w-xl mt-6">
                <h3 class="text-base font-semibold text-red-900 dark:text-red-200">{{ __('tenants.danger_zone') }}</h3>
                <p class="text-sm text-red-800 dark:text-red-300 mt-1">{!! __('tenants.delete_help_html') !!}</p>
                <button
                    wire:click="deleteTenant"
                    wire:confirm="{{ __('tenants.delete_confirm', ['name' => $tenant->name]) }}"
                    class="mt-3 px-3 py-2 text-sm text-white bg-red-600 rounded-md hover:bg-red-700"
                >{{ __('tenants.delete') }}</button>
            </div>
        @endcan
    @endif

    @if ($activeTab === 'members')
        @can('update', $tenant)
            <p class="text-sm text-gray-500 dark:text-slate-400 mb-3">{!! __('tenants.members_help_html') !!}</p>
            <form wire:submit="addMember" class="bg-white dark:bg-slate-800 shadow ring-1 ring-black ring-opacity-5 dark:ring-slate-600 rounded-md p-4 mb-6 grid grid-cols-1 md:grid-cols-[1fr_auto] gap-3 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('tenants.member_label') }}</label>
                    @if ($candidates->isEmpty())
                        <p class="mt-1 text-sm text-gray-500 dark:text-slate-400 italic">{{ __('tenants.no_candidates') }}</p>
                    @else
                        <select wire:model="newUserId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm">
                            <option value="">{{ __('common.select_short') }}</option>
                            @foreach ($candidates as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    @endif
                    @error('newUserId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" @disabled($candidates->isEmpty()) class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">{{ __('common.assign') }}</button>
            </form>
        @endcan

        <div class="bg-white dark:bg-slate-800 shadow ring-1 ring-black ring-opacity-5 dark:ring-slate-600 rounded-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                <thead class="bg-gray-50 dark:bg-slate-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">{{ __('common.name') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">{{ __('common.email') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700 text-sm">
                    @forelse ($members as $m)
                        <tr wire:key="m-{{ $m->id }}">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-slate-100">{{ $m->name }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-slate-400">{{ $m->email }}</td>
                            <td class="px-4 py-3 text-right">
                                @can('update', $tenant)
                                    <button
                                        wire:click="removeMember({{ $m->id }})"
                                        wire:confirm="{{ __('tenants.remove_member_confirm', ['name' => $m->name]) }}"
                                        class="text-xs text-red-600 hover:text-red-800"
                                    >{{ __('common.remove') }}</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500 dark:text-slate-400">{{ __('tenants.no_members') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
