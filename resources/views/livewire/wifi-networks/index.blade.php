<div>
    <x-page-header :title="__('wifi.title')" :subtitle="__('wifi.subtitle')">
        @can('create', App\Models\WifiNetwork::class)
            <button
                type="button"
                wire:click="openCreate"
                class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
            >
                <x-icon name="plus" class="h-4 w-4" />
                {{ __('wifi.new') }}
            </button>
        @endcan
    </x-page-header>

    <div class="mb-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('wifi.search_placeholder') }}"
            class="block w-64 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
        />
    </div>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('wifi.col_ssid') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('wifi.col_security') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('wifi.col_vlan') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('wifi.col_broadcasters') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('wifi.col_clients') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('wifi.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                @forelse ($networks as $net)
                    <tr wire:key="net-{{ $net->id }}">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <a href="{{ route('wifi-networks.show', $net) }}" wire:navigate class="text-indigo-700 hover:underline">{{ $net->ssid }}</a>
                            @if ($net->hidden_ssid)
                                <span class="ml-1 text-[10px] text-gray-500 italic">({{ __('wifi.hidden') }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $net->security_type ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $net->vlan_id ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $net->broadcasters_count }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $net->associations_count }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            @can('update', $net)
                                <button wire:click="openEdit({{ $net->id }})" class="text-indigo-600 hover:text-indigo-800">
                                    <x-icon name="pencil" class="h-4 w-4 inline" />
                                </button>
                            @endcan
                            @can('delete', $net)
                                <button wire:click="delete({{ $net->id }})" wire:confirm="{{ __('wifi.delete_confirm', ['name' => $net->ssid]) }}" class="text-red-600 hover:text-red-800">
                                    <x-icon name="trash" class="h-4 w-4 inline" />
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('wifi.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $networks->links() }}</div>

    @if ($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-md shadow-lg w-full max-w-lg p-6">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('wifi.form_edit') : __('wifi.form_new') }}</h2>
                <form wire:submit="save" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('wifi.label_ssid') }}</label>
                        <input type="text" wire:model="ssid" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('ssid')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('wifi.label_security') }}</label>
                            <input type="text" wire:model="securityType" placeholder="wpa2 / wpa3 / open / …" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('wifi.label_vlan') }}</label>
                            <input type="number" min="1" max="4094" wire:model="vlanIdField" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            @error('vlanIdField')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="hiddenSsid" class="rounded border-gray-300 text-indigo-600" />
                        {{ __('wifi.label_hidden_ssid') }}
                    </label>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('wifi.label_broadcasters') }}</label>
                        <select wire:model="broadcasterIds" multiple size="6" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach ($availableBroadcasters as $iface)
                                <option value="{{ $iface->id }}">{{ $iface->equipment?->name }} · {{ $iface->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">{{ __('wifi.help_broadcasters') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('wifi.label_notes') }}</label>
                        <textarea wire:model="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    </div>
                    <div class="flex justify-end gap-x-2 pt-2">
                        <button type="button" wire:click="$set('showForm', false)" class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
