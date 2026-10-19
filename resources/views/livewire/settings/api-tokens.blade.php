<div>
    <x-page-header :title="__('settings.tokens_title')" :subtitle="__('settings.tokens_subtitle')">
        <button type="button" wire:click="openCreate"
                class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
            <x-icon name="plus" class="h-4 w-4" />
            {{ __('settings.create_token') }}
        </button>
    </x-page-header>

    @if ($newPlainToken)
        <div class="bg-emerald-50 border border-emerald-200 rounded-md p-4 mb-6">
            <h3 class="font-semibold text-emerald-900">{{ __('settings.token_created') }}</h3>
            <p class="text-sm text-emerald-800 mt-1">{{ __('settings.token_copy_now') }}</p>
            <code class="block mt-2 p-2 rounded bg-emerald-100 text-emerald-950 font-mono text-xs break-all">{{ $newPlainToken }}</code>
            <button wire:click="$set('newPlainToken', null)" class="text-xs text-emerald-700 hover:text-emerald-900 underline mt-2">{{ __('settings.token_copied') }}</button>
        </div>
    @endif

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('common.name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('settings.abilities') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('settings.token_tenants') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('settings.expires') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('settings.last_used') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('settings.created') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                @forelse ($tokens as $t)
                    <tr wire:key="tok-{{ $t->id }}">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $t->name }}</td>
                        <td class="px-4 py-3 text-gray-600" title="{{ implode(', ', (array) $t->abilities) }}">
                            {{ $summaries[$t->id]['abilities'] }}
                            @foreach ($summaries[$t->id]['badges'] as $badge)
                                <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">{{ __('settings.ability_'.$badge.'_short') }}</span>
                            @endforeach
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $summaries[$t->id]['tenants'] }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            @if ($t->expires_at)
                                <span @class(['text-red-600' => $t->expires_at->isPast()])>{{ $t->expires_at->format('Y-m-d') }}</span>
                            @else
                                {{ __('settings.never') }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $t->last_used_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $t->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap space-x-3">
                            <button wire:click="openEdit({{ $t->id }})" class="text-indigo-600 hover:text-indigo-800 text-xs">{{ __('settings.edit_permissions') }}</button>
                            <button wire:click="revoke({{ $t->id }})" wire:confirm="{{ __('settings.revoke_confirm', ['name' => $t->name]) }}" class="text-red-600 hover:text-red-800 text-xs">{{ __('settings.revoke') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('settings.no_tokens') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h2 class="mt-8 mb-2 text-base font-semibold text-gray-900">{{ __('settings.apps_title') }}</h2>
    <p class="mb-3 text-sm text-gray-600">{{ __('settings.apps_subtitle') }}</p>
    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-x-auto" data-testid="connected-apps">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('settings.app') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('settings.abilities') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('settings.token_tenants') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('settings.connected_at') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('settings.last_used') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                @forelse ($grants as $g)
                    <tr wire:key="grant-{{ $g->id }}">
                        <td class="px-4 py-3">
                            <span class="font-medium text-gray-900">{{ $g->client?->name }}</span>
                            <span class="block text-xs text-gray-500">{{ $grantSummaries[$g->id]['host'] }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $grantSummaries[$g->id]['abilities'] }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $grantSummaries[$g->id]['tenants'] }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $g->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $grantSummaries[$g->id]['last_used'] }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap space-x-3">
                            <button wire:click="openEditGrant({{ $g->id }})" class="text-indigo-600 hover:text-indigo-800 text-xs">{{ __('settings.edit_permissions') }}</button>
                            <button wire:click="disconnect({{ $g->id }})" wire:confirm="{{ __('settings.disconnect_confirm', ['name' => $g->client?->name]) }}" class="text-red-600 hover:text-red-800 text-xs">{{ __('settings.disconnect') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('settings.no_apps') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-md shadow-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
                <h2 class="text-lg font-semibold mb-4">{{ $editingGrantId ? __('settings.form_edit_app') : ($editingId ? __('settings.form_edit_token') : __('settings.form_new_token')) }}</h2>
                <form wire:submit="save" class="space-y-5">
                    @unless ($editingGrantId)
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">{{ __('settings.token_name') }}</label>
                            <input type="text" wire:model="name" placeholder="{{ __('settings.token_name_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('settings.expires') }}</label>
                            <input type="date" wire:model="expiresAt" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            <p class="text-xs text-gray-500 mt-1">{{ __('settings.expires_hint') }}</p>
                            @error('expiresAt')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    @endunless

                    <x-token-permissions :resources="$resources" :actions="$actions" :special="$special" :tenants="$tenants" :tenant-scope="$tenantScope" />

                    <p class="text-xs text-gray-500">{{ __('settings.token_scope_note') }}</p>

                    <div class="flex justify-end gap-x-2">
                        <button type="button" wire:click="$set('showForm', false)" class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">{{ $editingId || $editingGrantId ? __('common.save') : __('settings.create_token') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
