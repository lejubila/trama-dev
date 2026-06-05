<div>
    <x-page-header :title="__('vpn.title')" :subtitle="__('vpn.subtitle')">
        @can('create', App\Models\VpnRemoteAccess::class)
            <button type="button" wire:click="openCreateRemote" class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                <x-icon name="plus" class="h-4 w-4" />
                {{ __('vpn.new_remote') }}
            </button>
        @endcan
        @can('create', App\Models\VpnSiteToSite::class)
            <button type="button" wire:click="openCreateSite" class="inline-flex items-center gap-x-1.5 rounded-md bg-violet-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-violet-700">
                <x-icon name="plus" class="h-4 w-4" />
                {{ __('vpn.new_site') }}
            </button>
        @endcan
    </x-page-header>

    <h2 class="text-base font-semibold text-gray-900 mb-2">{{ __('vpn.remote_heading') }}</h2>
    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden mb-8">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('vpn.col_name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('vpn.col_protocol') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('vpn.col_firewall') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('vpn.col_routing') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('vpn.col_network') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('vpn.col_clients') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('vpn.col_routed_vlans') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('vpn.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                @forelse ($remotes as $r)
                    <tr wire:key="ra-{{ $r->id }}">
                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('vpns.remote-access.show', $r) }}" wire:navigate class="text-indigo-700 hover:underline">{{ $r->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $r->protocol?->label() }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $r->firewallInterface?->equipment?->name }} · <span class="font-mono">{{ $r->firewallInterface?->name }}</span></td>
                        <td class="px-4 py-3 text-gray-600">{{ __('vpn.routing_mode_'.($r->routing_mode?->value ?? 'routed')) }}</td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $r->client_network_cidr ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $r->clients_count }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ is_array($r->routed_vlans) ? implode(',', $r->routed_vlans) : '—' }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            @can('update', $r)
                                <button wire:click="openEditRemote({{ $r->id }})" class="text-indigo-600 hover:text-indigo-800"><x-icon name="pencil" class="h-4 w-4 inline" /></button>
                            @endcan
                            @can('delete', $r)
                                <button wire:click="deleteRemote({{ $r->id }})" wire:confirm="{{ __('vpn.delete_confirm', ['name' => $r->name]) }}" class="text-red-600 hover:text-red-800"><x-icon name="trash" class="h-4 w-4 inline" /></button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">{{ __('vpn.empty_remote') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h2 class="text-base font-semibold text-gray-900 mb-2">{{ __('vpn.site_heading') }}</h2>
    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('vpn.col_name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('vpn.col_protocol') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('vpn.col_side_a') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('vpn.col_side_b') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('vpn.col_routed_vlans') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('vpn.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                @forelse ($sites as $s)
                    <tr wire:key="stos-{{ $s->id }}">
                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('vpns.site-to-site.show', $s) }}" wire:navigate class="text-indigo-700 hover:underline">{{ $s->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $s->protocol?->label() }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $s->endpointAInterface?->equipment?->name }} · <span class="font-mono">{{ $s->endpointAInterface?->name }}</span></td>
                        <td class="px-4 py-3 text-gray-600">{{ $s->endpointBInterface?->equipment?->name }} · <span class="font-mono">{{ $s->endpointBInterface?->name }}</span></td>
                        <td class="px-4 py-3 text-gray-600 text-xs">
                            A: {{ is_array($s->routed_vlans_a) ? implode(',', $s->routed_vlans_a) : '—' }}<br>
                            B: {{ is_array($s->routed_vlans_b) ? implode(',', $s->routed_vlans_b) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            @can('update', $s)
                                <button wire:click="openEditSite({{ $s->id }})" class="text-indigo-600 hover:text-indigo-800"><x-icon name="pencil" class="h-4 w-4 inline" /></button>
                            @endcan
                            @can('delete', $s)
                                <button wire:click="deleteSite({{ $s->id }})" wire:confirm="{{ __('vpn.delete_confirm', ['name' => $s->name]) }}" class="text-red-600 hover:text-red-800"><x-icon name="trash" class="h-4 w-4 inline" /></button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('vpn.empty_site') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($formType !== '')
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-md shadow-lg w-full max-w-lg p-6">
                <h2 class="text-lg font-semibold mb-4">
                    {{ $editingId
                        ? ($formType === 'remote' ? __('vpn.form_edit_remote') : __('vpn.form_edit_site'))
                        : ($formType === 'remote' ? __('vpn.form_new_remote') : __('vpn.form_new_site'))
                    }}
                </h2>
                <form wire:submit="save" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('vpn.label_name') }}</label>
                        <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('vpn.label_protocol') }}</label>
                        <select wire:model="protocol" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach ($protocols as $p)
                                <option value="{{ $p->value }}">{{ $p->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($formType === 'remote')
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('vpn.label_firewall') }}</label>
                            <select wire:model="firewallInterfaceId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="0">— {{ __('vpn.pick_firewall_iface') }} —</option>
                                @foreach ($firewallInterfaces as $iface)
                                    <option value="{{ $iface->id }}">{{ $iface->equipment?->name }} · {{ $iface->name }}</option>
                                @endforeach
                            </select>
                            @error('firewallInterfaceId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('vpn.label_routing_mode') }}</label>
                            <select wire:model="routingMode" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                @foreach ($routingModes as $m)
                                    <option value="{{ $m->value }}">{{ __('vpn.routing_mode_'.$m->value) }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">{{ __('vpn.routing_mode_hint') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('vpn.label_client_network_cidr') }}</label>
                            <div class="mt-1 flex gap-2">
                                <input type="text" wire:model="clientNetworkIp" placeholder="es. 10.10.0.0" class="block w-1/2 rounded-md border-gray-300 shadow-sm text-sm font-mono" />
                                <select wire:model="clientNetworkPrefix" class="block w-1/2 rounded-md border-gray-300 shadow-sm text-xs font-mono">
                                    @foreach ($prefixOptions as $opt)
                                        <option value="{{ $opt['prefix'] }}">/{{ $opt['prefix'] }} — {{ $opt['netmask'] }} — {{ $opt['bits'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('clientNetworkIp')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('vpn.label_routed_vlans') }}</label>
                            <input type="text" wire:model="routedVlans" placeholder="es. 10,20,30" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('vpn.label_endpoint_a') }}</label>
                                <select wire:model="endpointAInterfaceId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="0">— {{ __('vpn.pick_firewall_iface') }} —</option>
                                    @foreach ($firewallInterfaces as $iface)
                                        <option value="{{ $iface->id }}">{{ $iface->equipment?->name }} · {{ $iface->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('vpn.label_endpoint_b') }}</label>
                                <select wire:model="endpointBInterfaceId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="0">— {{ __('vpn.pick_firewall_iface') }} —</option>
                                    @foreach ($firewallInterfaces as $iface)
                                        <option value="{{ $iface->id }}">{{ $iface->equipment?->name }} · {{ $iface->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @error('endpointAInterfaceId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        @error('endpointBInterfaceId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('vpn.label_routed_vlans_a') }}</label>
                                <input type="text" wire:model="routedVlans" placeholder="es. 10,20" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('vpn.label_routed_vlans_b') }}</label>
                                <input type="text" wire:model="routedVlansB" placeholder="es. 100,200" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('vpn.label_routed_networks_a') }}</label>
                                <textarea wire:model="routedNetworksA" rows="3" placeholder="10.0.0.0/24&#10;10.1.0.0/16" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono"></textarea>
                                @error('routedNetworksA')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('vpn.label_routed_networks_b') }}</label>
                                <textarea wire:model="routedNetworksB" rows="3" placeholder="192.168.10.0/24" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono"></textarea>
                                @error('routedNetworksB')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('vpn.label_notes') }}</label>
                        <textarea wire:model="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                    </div>
                    <div class="flex justify-end gap-x-2 pt-2">
                        <button type="button" wire:click="$set('formType', '')" class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
