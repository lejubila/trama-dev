<div>
    <x-page-header :title="$document ? __('documents.edit_title') : __('documents.new_title')"
                   :subtitle="__('documents.editor_subtitle')">
        <a href="{{ route('documents.index') }}" wire:navigate
           class="text-sm text-gray-600 hover:text-gray-800">{{ __('documents.back') }}</a>
    </x-page-header>

    <form wire:submit="save" class="space-y-6">

        {{-- Header --}}
        <div data-guide="documents.info" class="bg-white shadow ring-1 ring-black/5 rounded-md p-4 space-y-3">
            <h3 class="font-semibold text-gray-700">{{ __('documents.info_heading') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">{{ __('common.title') }}</label>
                    <input type="text" wire:model="title"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    @error('title')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('common.date') }}</label>
                    <input type="date" wire:model="documentDate"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    @error('documentDate')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('common.language') }}</label>
                    <select wire:model="locale" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        @foreach ($locales as $code)
                            <option value="{{ $code }}">{{ __('common.language_'.$code) }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">{{ __('documents.language_help') }}</p>
                    @error('locale')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('common.description') }}</label>
                <textarea wire:model="description" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                @error('description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="flex items-center gap-x-6 gap-y-2 text-sm pt-1 flex-wrap">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model="includeCover" class="rounded border-gray-300 text-indigo-600">
                    {{ __('documents.include_cover') }}
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model="includeToc" class="rounded border-gray-300 text-indigo-600">
                    {{ __('documents.include_toc') }}
                </label>
                <label class="inline-flex items-center gap-2" title="{{ __('documents.include_rear_title') }}">
                    <input type="checkbox" wire:model="rackIncludeRear" class="rounded border-gray-300 text-indigo-600">
                    {{ __('documents.include_rear') }}
                </label>
                <label class="inline-flex items-center gap-2" title="{{ __('documents.include_floorplan_title') }}">
                    <input type="checkbox" wire:model="roomsIncludeFloorplan" class="rounded border-gray-300 text-indigo-600">
                    {{ __('documents.include_floorplan') }}
                </label>
            </div>
            <p class="mt-3 text-xs text-gray-500">
                {{ __('documents.hierarchy_help') }}
            </p>
        </div>

        {{-- Sezione: Contenuti (albero gerarchico Sedi → Locali → Rack → Dispositivi) --}}
        <div data-guide="documents.sections" class="bg-white shadow ring-1 ring-black/5 rounded-md p-4 space-y-4">
            <h3 class="font-semibold text-gray-700">{{ __('documents.contents') }}</h3>

            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                <span class="text-gray-500">{{ __('documents.levels') }}</span>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model.live="sitesEnabled" class="rounded border-gray-300 text-indigo-600"> {{ __('documents.level_sites') }}
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model.live="roomsEnabled" class="rounded border-gray-300 text-indigo-600"> {{ __('documents.level_rooms') }}
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model.live="racksEnabled" class="rounded border-gray-300 text-indigo-600"> {{ __('documents.level_racks') }}
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model.live="equipmentEnabled" class="rounded border-gray-300 text-indigo-600"> {{ __('documents.level_equipment') }}
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('documents.sites_description') }}</label>
                    <textarea wire:model="sitesDescription" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('documents.rooms_description') }}</label>
                    <textarea wire:model="roomsDescription" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                </div>
            </div>

            <p class="text-xs text-gray-500">
                {{ __('documents.tree_help') }}
            </p>

            <div class="border border-gray-200 rounded p-2 max-h-[28rem] overflow-y-auto" style="display:flex; flex-direction:column; gap:8px;">
                @forelse ($tree as $siteNode)
                    @php $site = $siteNode->model; @endphp
                    <div wire:key="site-{{ $site->id }}" style="order: {{ $loop->index }}">
                        <div class="flex items-center gap-2 text-base py-1">
                            <input type="checkbox" value="{{ $site->id }}" wire:model.live="sitesIds"
                                   class="rounded border-gray-300 text-indigo-600">
                            @if ($siteNode->selected)
                                @include('livewire.documents._reorder', ['id' => $site->id, 'method' => 'moveSite', 'canUp' => $siteNode->canUp, 'canDown' => $siteNode->canDown])
                            @endif
                            <span class="font-medium">{{ $site->name }}</span>
                        </div>

                        @if ($siteNode->selected)
                            <div class="ml-2 mt-1 border-l border-gray-200 pl-4" style="display:flex; flex-direction:column; gap:8px;">
                                @forelse ($siteNode->rooms as $roomNode)
                                    @php $room = $roomNode->model; @endphp
                                    <div wire:key="room-{{ $room->id }}" style="order: {{ $loop->index }}">
                                        <div class="flex items-center gap-2 text-base py-1">
                                            <input type="checkbox" value="{{ $room->id }}" wire:model.live="roomsIds"
                                                   class="rounded border-gray-300 text-indigo-600">
                                            @if ($roomNode->selected)
                                                @include('livewire.documents._reorder', ['id' => $room->id, 'method' => 'moveRoom', 'canUp' => $roomNode->canUp, 'canDown' => $roomNode->canDown])
                                            @endif
                                            <span>{{ $room->name }}</span>
                                        </div>

                                        @if ($roomNode->selected)
                                            <div class="ml-2 mt-1 border-l border-gray-200 pl-4" style="display:flex; flex-direction:column; gap:8px;">
                                                <div style="display:flex; flex-direction:column; gap:8px;">
                                                @foreach ($roomNode->racks as $rackNode)
                                                    @php $rack = $rackNode->model; @endphp
                                                    <div wire:key="rack-{{ $rack->id }}" style="order: {{ $loop->index }}">
                                                        <div class="flex items-center gap-2 text-base py-1">
                                                            <input type="checkbox" value="{{ $rack->id }}" wire:model.live="racksIds"
                                                                   class="rounded border-gray-300 text-indigo-600">
                                                            @if ($rackNode->selected)
                                                                @include('livewire.documents._reorder', ['id' => $rack->id, 'method' => 'moveRack', 'canUp' => $rackNode->canUp, 'canDown' => $rackNode->canDown])
                                                            @endif
                                                            <span class="text-gray-700">{{ __('documents.rack_name', ['name' => $rack->name]) }}</span>
                                                        </div>

                                                        @if ($rackNode->selected && $rackNode->equipment->isNotEmpty())
                                                            <div class="ml-2 mt-1 border-l border-gray-200 pl-4" style="display:flex; flex-direction:column; gap:8px;">
                                                                @foreach ($rackNode->equipment as $eqNode)
                                                                    @php $eq = $eqNode->model; @endphp
                                                                    <div class="flex items-center gap-2 text-base py-1" wire:key="eq-{{ $eq->id }}" style="order: {{ $loop->index }}">
                                                                        <input type="checkbox" value="{{ $eq->id }}" wire:model.live="equipmentIds"
                                                                               class="rounded border-gray-300 text-indigo-600">
                                                                        @if ($eqNode->selected)
                                                                            @include('livewire.documents._reorder', ['id' => $eq->id, 'method' => 'moveEquipment', 'canUp' => $eqNode->canUp, 'canDown' => $eqNode->canDown])
                                                                        @endif
                                                                        <span class="text-gray-600">{{ $eq->name }}</span>
                                                                        @if ($eqNode->selected)
                                                                            <label class="inline-flex items-center gap-1 text-sm text-gray-500 ml-2">
                                                                                <input type="checkbox" wire:click="toggleEquipmentPorts({{ $eq->id }})"
                                                                                       @checked(! in_array($eq->id, $equipmentPortsExcluded))
                                                                                       class="rounded border-gray-300 text-indigo-600">
                                                                                {{ __('documents.ports') }}
                                                                            </label>
                                                                        @endif
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                                </div>

                                                @if ($roomNode->unracked->isNotEmpty())
                                                    <div style="display:flex; flex-direction:column; gap:8px;">
                                                    <div class="text-sm text-gray-400 pt-1" style="order:-1">{{ __('documents.unracked') }}</div>
                                                    @foreach ($roomNode->unracked as $eqNode)
                                                        @php $eq = $eqNode->model; @endphp
                                                        <div class="flex items-center gap-2 text-base py-1" wire:key="eq-{{ $eq->id }}" style="order: {{ $loop->index }}">
                                                            <input type="checkbox" value="{{ $eq->id }}" wire:model.live="equipmentIds"
                                                                   class="rounded border-gray-300 text-indigo-600">
                                                            @if ($eqNode->selected)
                                                                @include('livewire.documents._reorder', ['id' => $eq->id, 'method' => 'moveEquipment', 'canUp' => $eqNode->canUp, 'canDown' => $eqNode->canDown])
                                                            @endif
                                                            <span class="text-gray-600">{{ $eq->name }}</span>
                                                            @if ($eqNode->selected)
                                                                <label class="inline-flex items-center gap-1 text-sm text-gray-500 ml-2">
                                                                    <input type="checkbox" wire:click="toggleEquipmentPorts({{ $eq->id }})"
                                                                           @checked(! in_array($eq->id, $equipmentPortsExcluded))
                                                                           class="rounded border-gray-300 text-indigo-600">
                                                                    {{ __('documents.ports') }}
                                                                </label>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-xs text-gray-400 italic">{{ __('documents.no_rooms') }}</p>
                                @endforelse
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500 italic">{{ __('documents.no_sites') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Sezione: Topologie (snapshot) — selezione + orientamento --}}
        <div data-guide="documents.topologies" class="bg-white shadow ring-1 ring-black/5 rounded-md">
            <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                <label class="inline-flex items-center gap-3">
                    <input type="checkbox" wire:model.live="topologiesEnabled"
                           class="rounded border-gray-300 text-indigo-600">
                    <span class="font-semibold text-gray-700">{{ __('documents.topologies') }}</span>
                </label>
                <span class="text-xs text-gray-500">
                    {{ __('common.selected_count', ['count' => count($topologiesItems), 'total' => $snapshotCount]) }}
                </span>
            </div>
            <div class="p-4 space-y-3 @if (!$topologiesEnabled) opacity-50 pointer-events-none @endif">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('common.section_description') }}</label>
                    <textarea wire:model="topologiesDescription" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                </div>
                @if ($snapshotCount === 0)
                    <p class="text-sm text-gray-500 italic">{{ __('documents.no_snapshots') }}</p>
                @else
                    <div class="space-y-2 max-h-80 overflow-y-auto border border-gray-200 rounded p-2">
                        <div style="display:flex; flex-direction:column; gap:8px;">
                        @foreach ($selectedSnaps as $snap)
                            <div class="flex items-center gap-3 py-1 text-sm bg-indigo-50 rounded px-2" wire:key="snap-sel-{{ $snap->id }}" style="order: {{ $loop->index }}">
                                @include('livewire.documents._reorder', ['id' => $snap->id, 'method' => 'moveTopology', 'canUp' => $loop->first === false, 'canDown' => $loop->last === false])
                                <input type="checkbox" checked
                                       wire:click="toggleSnapshot({{ $snap->id }})"
                                       class="rounded border-gray-300 text-indigo-600">
                                <div class="flex-1">
                                    <div class="font-medium">{{ $snap->title }}</div>
                                    <div class="text-xs text-gray-500">{{ $snap->snapshot_date->format('d/m/Y') }}</div>
                                </div>
                                <select wire:change="setSnapshotOrientation({{ $snap->id }}, $event.target.value)"
                                        class="rounded border-gray-300 text-xs">
                                    <option value="portrait" @selected($topologiesItems[$snap->id] === 'portrait')>{{ __('documents.portrait') }}</option>
                                    <option value="landscape" @selected($topologiesItems[$snap->id] === 'landscape')>{{ __('documents.landscape') }}</option>
                                </select>
                            </div>
                        @endforeach
                        </div>
                        @foreach ($restSnaps as $snap)
                            <div class="flex items-center gap-3 py-1 text-sm rounded px-2" wire:key="snap-rest-{{ $snap->id }}">
                                <input type="checkbox"
                                       wire:click="toggleSnapshot({{ $snap->id }})"
                                       class="rounded border-gray-300 text-indigo-600">
                                <div class="flex-1">
                                    <div class="font-medium">{{ $snap->title }}</div>
                                    <div class="text-xs text-gray-500">{{ $snap->snapshot_date->format('d/m/Y') }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Sezione: Reti Wi-Fi --}}
        <div data-guide="documents.wifi" class="bg-white shadow ring-1 ring-black/5 rounded-md">
            <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                <label class="inline-flex items-center gap-3">
                    <input type="checkbox" wire:model.live="wifiEnabled" class="rounded border-gray-300 text-indigo-600">
                    <span class="font-semibold text-gray-700">{{ __('documents.wifi') }}</span>
                </label>
                <span class="text-xs text-gray-500">{{ __('common.selected_female_count', ['count' => count($wifiIds), 'total' => $allWifi->count()]) }}</span>
            </div>
            <div class="p-4 space-y-3 @if (!$wifiEnabled) opacity-50 pointer-events-none @endif">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('common.section_description') }}</label>
                    <textarea wire:model="wifiDescription" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                </div>
                @if ($allWifi->isEmpty())
                    <p class="text-sm text-gray-500 italic">{{ __('documents.no_wifi') }}</p>
                @else
                    <div class="space-y-1 max-h-72 overflow-y-auto border border-gray-200 rounded p-2">
                        @foreach ($allWifi as $w)
                            <label class="flex items-center gap-2 py-1 text-sm" wire:key="wifi-{{ $w->id }}">
                                <input type="checkbox" wire:model.live="wifiIds" value="{{ $w->id }}"
                                       class="rounded border-gray-300 text-indigo-600">
                                <span class="font-medium">{{ $w->ssid }}</span>
                                <span class="text-xs text-gray-500">
                                    @if ($w->vlan_id) VLAN {{ $w->vlan_id }} @endif
                                    @if ($w->security_type) · {{ $w->security_type }} @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Sezione: VPN --}}
        <div data-guide="documents.vpn" class="bg-white shadow ring-1 ring-black/5 rounded-md">
            <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                <label class="inline-flex items-center gap-3">
                    <input type="checkbox" wire:model.live="vpnEnabled" class="rounded border-gray-300 text-indigo-600">
                    <span class="font-semibold text-gray-700">VPN</span>
                </label>
                <span class="text-xs text-gray-500">
                    {{ __('common.selected_female_count', ['count' => count($vpnRemoteIds) + count($vpnSiteIds), 'total' => $allVpnRemote->count() + $allVpnSite->count()]) }}
                </span>
            </div>
            <div class="p-4 space-y-3 @if (!$vpnEnabled) opacity-50 pointer-events-none @endif">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('common.section_description') }}</label>
                    <textarea wire:model="vpnDescription" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <h4 class="text-xs font-semibold text-gray-600 uppercase mb-1">{{ __('documents.remote_access') }}</h4>
                        @if ($allVpnRemote->isEmpty())
                            <p class="text-xs text-gray-500 italic">{{ __('documents.no_vpn_ra') }}</p>
                        @else
                            <div class="space-y-1 max-h-60 overflow-y-auto border border-gray-200 rounded p-2">
                                @foreach ($allVpnRemote as $v)
                                    <label class="flex items-center gap-2 py-1 text-sm" wire:key="vpn-ra-{{ $v->id }}">
                                        <input type="checkbox" wire:model.live="vpnRemoteIds" value="{{ $v->id }}"
                                               class="rounded border-gray-300 text-indigo-600">
                                        <span class="font-medium">{{ $v->name }}</span>
                                        <span class="text-xs text-gray-500">{{ $v->protocol?->label() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div>
                        <h4 class="text-xs font-semibold text-gray-600 uppercase mb-1">{{ __('documents.site_to_site') }}</h4>
                        @if ($allVpnSite->isEmpty())
                            <p class="text-xs text-gray-500 italic">{{ __('documents.no_vpn_s2s') }}</p>
                        @else
                            <div class="space-y-1 max-h-60 overflow-y-auto border border-gray-200 rounded p-2">
                                @foreach ($allVpnSite as $v)
                                    <label class="flex items-center gap-2 py-1 text-sm" wire:key="vpn-stos-{{ $v->id }}">
                                        <input type="checkbox" wire:model.live="vpnSiteIds" value="{{ $v->id }}"
                                               class="rounded border-gray-300 text-indigo-600">
                                        <span class="font-medium">{{ $v->name }}</span>
                                        <span class="text-xs text-gray-500">{{ $v->protocol?->label() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div data-guide="documents.save" class="flex items-center gap-3 pt-2">
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="px-4 py-2 rounded bg-indigo-600 text-white font-medium hover:bg-indigo-700 disabled:opacity-50">
                {{ __('documents.save_generate') }}
            </button>
            <span wire:loading wire:target="save" class="text-sm text-gray-500">{{ __('documents.generating') }}</span>
            <a href="{{ route('documents.index') }}" wire:navigate
               class="text-sm text-gray-600 hover:text-gray-800">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
