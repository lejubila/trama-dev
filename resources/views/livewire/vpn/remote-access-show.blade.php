<div>
    <x-page-header :title="$vpn->name" :subtitle="$vpn->protocol?->label() . ' · ' . __('vpn.subtitle_remote')">
        <a href="{{ route('vpns.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-800">← {{ __('vpn.back') }}</a>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4">
            <h3 class="text-sm font-semibold mb-3">{{ __('vpn.firewall_heading') }}</h3>
            <p class="text-sm">
                {{ $vpn->firewallInterface?->equipment?->name }} ·
                <span class="font-mono">{{ $vpn->firewallInterface?->name }}</span>
            </p>
            <p class="text-xs text-gray-500 mt-2">
                {{ __('vpn.routed_vlans_label') }}:
                {{ is_array($vpn->routed_vlans) ? implode(',', $vpn->routed_vlans) : '—' }}
            </p>
            @if ($vpn->notes)
                <p class="mt-3 text-xs text-gray-600 whitespace-pre-wrap">{{ $vpn->notes }}</p>
            @endif
        </div>

        <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold">{{ __('vpn.clients_heading') }}</h3>
                @can('update', $vpn)
                    <button wire:click="openAttach" class="inline-flex items-center gap-1 rounded-md bg-indigo-600 text-white text-xs px-2 py-1 hover:bg-indigo-500">
                        <x-icon name="plus" class="h-3.5 w-3.5" />
                        {{ __('vpn.attach_client') }}
                    </button>
                @endcan
            </div>
            @if ($clients->isEmpty())
                <p class="text-sm text-gray-500 italic">{{ __('vpn.no_clients') }}</p>
            @else
                <ul class="text-sm divide-y divide-gray-100">
                    @foreach ($clients as $c)
                        <li class="py-2 flex items-center justify-between">
                            <span>
                                {{ $c->clientInterface?->equipment?->name }} ·
                                <span class="font-mono">{{ $c->clientInterface?->name }}</span>
                                @if ($c->username)
                                    <span class="text-xs text-gray-500">({{ $c->username }})</span>
                                @endif
                            </span>
                            @can('update', $vpn)
                                <button wire:click="detach({{ $c->id }})" wire:confirm="{{ __('vpn.detach_confirm') }}" class="text-red-600 hover:text-red-800 text-xs">
                                    <x-icon name="trash" class="h-3.5 w-3.5 inline" />
                                </button>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    @if ($showAttachForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-md shadow-lg w-full max-w-md p-6">
                <h2 class="text-lg font-semibold mb-4">{{ __('vpn.attach_title') }}</h2>
                <form wire:submit="attach" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('vpn.label_client') }}</label>
                        <select wire:model.live="clientEquipmentId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="0">— {{ __('vpn.pick_client') }} —</option>
                            @foreach ($candidateClients as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->type?->label() }})</option>
                            @endforeach
                        </select>
                        @error('clientEquipmentId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model.live="createNewInterface" class="rounded border-gray-300 text-indigo-600" />
                        {{ __('vpn.create_new_iface') }}
                    </label>
                    @if (! $createNewInterface)
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('vpn.label_client_iface') }}</label>
                            <select wire:model="clientInterfaceId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="0">— {{ __('vpn.pick_iface') }} —</option>
                                @foreach ($virtualIfaces as $iface)
                                    <option value="{{ $iface->id }}">{{ $iface->name }}</option>
                                @endforeach
                            </select>
                            @if ($clientEquipmentId > 0 && $virtualIfaces->isEmpty())
                                <p class="text-xs text-amber-600 mt-1">{{ __('vpn.no_virtual_ifaces') }}</p>
                            @endif
                        </div>
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('vpn.label_username') }}</label>
                        <input type="text" wire:model="username" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    <div class="flex justify-end gap-x-2 pt-2">
                        <button type="button" wire:click="$set('showAttachForm', false)" class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">{{ __('vpn.attach_action') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
