<div>
    <x-page-header :title="$network->ssid"
        :subtitle="($network->security_type ?? '—').' · VLAN '.($network->vlan_id ?? '—')">
        <a href="{{ route('wifi-networks.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-800">← {{ __('wifi.back') }}</a>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4">
            <h3 class="text-sm font-semibold mb-3">{{ __('wifi.broadcasters_heading') }}</h3>
            @if ($network->broadcasters->isEmpty())
                <p class="text-sm text-gray-500 italic">{{ __('wifi.no_broadcasters') }}</p>
            @else
                <ul class="text-sm divide-y divide-gray-100">
                    @foreach ($network->broadcasters as $bc)
                        <li class="py-2 flex items-center justify-between">
                            <span>{{ $bc->equipment?->name }} · <span class="font-mono">{{ $bc->name }}</span></span>
                            <span class="text-xs text-gray-500">{{ $bc->equipment?->type?->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold">{{ __('wifi.clients_heading') }}</h3>
                @can('update', $network)
                    <button wire:click="openAttach" class="inline-flex items-center gap-1 rounded-md bg-indigo-600 text-white text-xs px-2 py-1 hover:bg-indigo-500">
                        <x-icon name="plus" class="h-3.5 w-3.5" />
                        {{ __('wifi.attach_client') }}
                    </button>
                @endcan
            </div>
            @if ($associations->isEmpty())
                <p class="text-sm text-gray-500 italic">{{ __('wifi.no_clients') }}</p>
            @else
                <ul class="text-sm divide-y divide-gray-100">
                    @foreach ($associations as $a)
                        <li class="py-2 flex items-center justify-between">
                            <span>{{ $a->clientInterface?->equipment?->name }} · <span class="font-mono">{{ $a->clientInterface?->name }}</span></span>
                            @can('update', $network)
                                <button wire:click="detach({{ $a->id }})" wire:confirm="{{ __('wifi.detach_confirm') }}" class="text-red-600 hover:text-red-800 text-xs">
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
                <h2 class="text-lg font-semibold mb-4">{{ __('wifi.attach_title') }}</h2>
                <form wire:submit="attach" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('wifi.label_client') }}</label>
                        <select wire:model.live="clientEquipmentId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="0">— {{ __('wifi.pick_client') }} —</option>
                            @foreach ($clients as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->type?->label() }})</option>
                            @endforeach
                        </select>
                        @error('clientEquipmentId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model.live="createNewInterface" class="rounded border-gray-300 text-indigo-600" />
                        {{ __('wifi.create_new_iface') }}
                    </label>
                    @if (! $createNewInterface)
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('wifi.label_client_iface') }}</label>
                            <select wire:model="clientInterfaceId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="0">— {{ __('wifi.pick_iface') }} —</option>
                                @foreach ($clientWirelessIfaces as $iface)
                                    <option value="{{ $iface->id }}">{{ $iface->name }}</option>
                                @endforeach
                            </select>
                            @if ($clientEquipmentId > 0 && $clientWirelessIfaces->isEmpty())
                                <p class="text-xs text-amber-600 mt-1">{{ __('wifi.no_wireless_ifaces') }}</p>
                            @endif
                            @error('clientInterfaceId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    <div class="flex justify-end gap-x-2 pt-2">
                        <button type="button" wire:click="$set('showAttachForm', false)" class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">{{ __('wifi.attach_action') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
