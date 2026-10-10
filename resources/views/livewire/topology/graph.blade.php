<div
    x-data="{ fs: false, filters: false }"
    x-effect="$dispatch('topology-fullscreen', { on: fs }); $nextTick(() => setTimeout(() => { if (window.cy) { window.cy.resize(); window.cy.fit(undefined, 30); } }, 60))"
    @topology-toggle-fs.window="fs = !fs"
    @topology-toggle-filters.window="filters = !filters"
    @keydown.escape.window="fs && (fs = false)"
    :class="fs ? 'fixed inset-0 z-50 bg-gray-100 dark:bg-slate-900 p-3' : ''"
    :style="fs ? 'height: 100vh' : 'height: calc(100vh - 8rem)'"
    class="flex flex-col"
>
    <div x-show="!fs">
        <x-page-header :title="__('topology.title')" :subtitle="__('topology.subtitle')" />
    </div>

    {{-- Filtri: sempre visibili in modalità standard; a schermo intero
         compaiono solo quando si attiva il toggle (in alto a sinistra sulla mappa). --}}
    <div
        x-show="!fs || filters"
        class="flex flex-col gap-3 p-3 bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md mb-3"
    >
        {{-- Riga 1: filtri --}}
        <div class="flex flex-wrap items-center gap-3">
        <select wire:model.live="siteId" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="0">{{ __('common.all_sites') }}</option>
            @foreach ($sites as $s)
                <option value="{{ $s->id }}">{{ $s->name }}@if (filled($s->address)) — {{ $s->address }}@endif</option>
            @endforeach
        </select>

        <select wire:model.live="roomFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="0">{{ __('common.all_rooms') }}</option>
            @foreach ($rooms as $r)
                <option value="{{ $r->id }}">{{ $r->name }}@if ($siteId === 0 && $r->site) ({{ $r->site->name }})@endif</option>
            @endforeach
        </select>

        <select wire:model.live="layout" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="cose-bilkent">{{ __('topology.layout_cose') }}</option>
            <option value="dagre">{{ __('topology.layout_dagre') }}</option>
            <option value="breadthfirst">{{ __('topology.layout_breadthfirst') }}</option>
            <option value="circle">{{ __('topology.layout_circle') }}</option>
            <option value="grid">{{ __('topology.layout_grid') }}</option>
        </select>

        <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">{{ __('common.all_statuses') }}</option>
            @foreach ($statuses as $s)
                <option value="{{ $s->value }}">{{ $s->label() }}</option>
            @endforeach
        </select>

        <div x-data="{ open: false }" @click.away="open = false" class="relative">
            <button type="button" @click="open = !open"
                class="rounded-md border border-gray-300 shadow-sm text-sm px-3 py-2 bg-white inline-flex items-center gap-1">
                {{ __('topology.types') }} @if (count($filterTypes)) <span class="text-indigo-600 font-medium">({{ count($filterTypes) }})</span> @endif
                <span class="text-gray-400">▾</span>
            </button>
            <div x-show="open" x-cloak class="absolute z-20 mt-1 max-h-80 overflow-auto rounded-md bg-white p-2 shadow-lg ring-1 ring-black/5 w-56">
                @if (count($filterTypes))
                    <button type="button" wire:click="$set('filterTypes', [])"
                        class="block w-full text-left px-2 py-1 text-xs text-indigo-600 hover:underline">{{ __('topology.select_all') }}</button>
                @endif
                @foreach (\App\Enums\EquipmentType::groupedCases() as $group => $items)
                    <div class="px-2 pt-2 text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $group }}</div>
                    @foreach ($items as $t)
                        @php $checked = $filterTypes === [] || in_array($t->value, $filterTypes, true); @endphp
                        <button type="button" wire:click="toggleType('{{ $t->value }}')"
                            wire:key="ft-{{ $t->value }}"
                            class="w-full flex items-center gap-2 px-2 py-1 text-sm hover:bg-gray-50 text-left">
                            <span class="inline-flex h-4 w-4 items-center justify-center rounded border {{ $checked ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-gray-300 bg-white' }}">
                                @if ($checked) <span class="text-xs leading-none">✓</span> @endif
                            </span>
                            <span class="inline-block h-2.5 w-2.5 rounded-full bg-{{ $t->color() }}-500"></span>
                            {{ $t->label() }}
                        </button>
                    @endforeach
                @endforeach
            </div>
        </div>

        <div x-data="{ open: false }" @click.away="open = false" class="relative">
            <button type="button" @click="open = !open"
                class="rounded-md border border-gray-300 shadow-sm text-sm px-3 py-2 bg-white inline-flex items-center gap-1">
                Tag @if (count($tagFilters)) <span class="text-indigo-600 font-medium">({{ count($tagFilters) }})</span> @endif
                <span class="text-gray-400">▾</span>
            </button>
            <div x-show="open" x-cloak class="absolute z-20 mt-1 max-h-60 overflow-auto rounded-md bg-white p-2 shadow-lg ring-1 ring-black/5 min-w-[12rem]">
                @forelse ($allTags as $tag)
                    <label class="flex items-center gap-2 px-2 py-1 text-sm cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" value="{{ $tag->id }}" wire:model.live="tagFilters"
                            class="rounded border-gray-300 text-indigo-600" />
                        <span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: {{ $tag->color }}"></span>
                        {{ $tag->name }}
                    </label>
                @empty
                    <span class="block px-2 py-1 text-xs text-gray-400">{{ __('topology.no_tags') }}</span>
                @endforelse
            </div>
        </div>

        <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
            <span class="font-medium">VLAN</span>
            <input
                type="number" min="1" max="4094"
                wire:model.live.debounce.500ms="vlanFilter"
                placeholder="es. 10"
                class="w-24 rounded-md border-gray-300 shadow-sm text-sm"
            />
        </label>
        </div>

        {{-- Riga 2: flag a sinistra, azioni allineate a destra --}}
        <div class="flex flex-wrap items-center gap-3">
        <label class="inline-flex items-center gap-1 text-xs text-gray-700" title="{{ __('topology.show_hidden_title') }}">
            <input type="checkbox" wire:model.live="includeHidden" class="rounded border-gray-300 text-indigo-600" />
            {{ __('topology.show_hidden') }}
        </label>

        <label class="inline-flex items-center gap-1 text-xs text-gray-700" title="{{ __('topology.hide_patch_title') }}">
            <input type="checkbox" wire:model.live="hidePatchPanels" class="rounded border-gray-300 text-indigo-600" />
            {{ __('topology.hide_patch') }}
        </label>

        <label class="inline-flex items-center gap-1 text-xs text-gray-700" title="{{ __('topology.hide_wifi_title') }}">
            <input type="checkbox" wire:model.live="hideWifi" class="rounded border-gray-300 text-indigo-600" />
            {{ __('topology.hide_wifi') }}
        </label>

        <label class="inline-flex items-center gap-1 text-xs text-gray-700" title="{{ __('topology.hide_vpn_title') }}">
            <input type="checkbox" wire:model.live="hideVpn" class="rounded border-gray-300 text-indigo-600" />
            {{ __('topology.hide_vpn') }}
        </label>

        <label class="inline-flex items-center gap-1 text-xs text-gray-700" title="{{ __('topology.group_rack_title') }}">
            <input type="checkbox" wire:model.live="groupByRack" class="rounded border-gray-300 text-indigo-600" />
            {{ __('topology.group_rack') }}
        </label>

        <label class="inline-flex items-center gap-1 text-xs text-gray-700" title="{{ __('topology.group_room_title') }}">
            <input type="checkbox" wire:model.live="groupByRoom" class="rounded border-gray-300 text-indigo-600" />
            {{ __('topology.group_room') }}
        </label>

        <label class="inline-flex items-center gap-1 text-xs text-gray-700" title="{{ __('topology.group_site_title') }}">
            <input type="checkbox" wire:model.live="groupBySite" class="rounded border-gray-300 text-indigo-600" />
            {{ __('topology.group_site') }}
        </label>

        <label class="inline-flex items-center gap-1 text-xs text-gray-700" title="{{ __('topology.group_hypervisor_title') }}">
            <input type="checkbox" wire:model.live="groupByHypervisor" class="rounded border-gray-300 text-indigo-600" />
            {{ __('topology.group_hypervisor') }}
        </label>

        <button wire:click="clearFilters" class="text-xs px-2 py-1 text-gray-500 hover:text-gray-700 underline ml-auto">
            {{ __('common.reset_filters') }}
        </button>

        <div class="flex items-center gap-1">
            <button x-data x-on:click="window.cy && window.cy.fit()" class="text-xs px-2 py-1 rounded border bg-white text-gray-700">{{ __('topology.fit') }}</button>
            <button x-data x-on:click="window.cy && window.cy.zoom(window.cy.zoom() * 1.2)" class="text-xs px-2 py-1 rounded border bg-white text-gray-700">+</button>
            <button x-data x-on:click="window.cy && window.cy.zoom(window.cy.zoom() / 1.2)" class="text-xs px-2 py-1 rounded border bg-white text-gray-700">−</button>
            <button x-data x-on:click="window.dispatchEvent(new CustomEvent('topology:export-png'))" class="text-xs px-2 py-1 rounded border bg-white text-gray-700">PNG</button>

            @if ($canEdit)
                <button
                    x-data
                    data-guide="topology.save-snapshot"
                    x-on:click="Livewire.dispatch('snapshot-open', { viewState: {
                        siteId: $wire.siteId,
                        roomFilter: $wire.roomFilter,
                        statusFilter: $wire.statusFilter,
                        vlanFilter: $wire.vlanFilter,
                        tagFilters: $wire.tagFilters,
                        layout: $wire.layout,
                        filterTypes: $wire.filterTypes,
                        includeHidden: $wire.includeHidden,
                        groupByRack: $wire.groupByRack,
                        groupBySite: $wire.groupBySite,
                        groupByRoom: $wire.groupByRoom,
                        groupByHypervisor: $wire.groupByHypervisor,
                        hidePatchPanels: $wire.hidePatchPanels,
                        hideWifi: $wire.hideWifi,
                        hideVpn: $wire.hideVpn,
                        nodePositions: window.cy
                            ? Object.fromEntries(window.cy.nodes().map(n => {
                                const p = n.position();
                                return [n.id(), [Math.round(p.x), Math.round(p.y)]];
                              }))
                            : {},
                        zoom: window.cy ? window.cy.zoom() : 1,
                        pan:  window.cy ? [Math.round(window.cy.pan().x), Math.round(window.cy.pan().y)] : [0, 0],
                        portSettings: window._topologyPortSettings || {},
                        nodeLabelPositions: window._topologyNodeLabelPositions || {},
                        sessionHiddenIds: window._topologySessionHiddenIds || [],
                        vpnNodeDetails: window._topologyVpnNodeDetails || {},
                    }})"
                    class="text-xs px-2 py-1 rounded border bg-white text-gray-700"
                >{{ __('topology.save_snapshot') }}</button>
            @endif
        </div>
        </div>
    </div>

    @if ($canEdit)
        <livewire:topology.snapshot-save-modal />
    @endif

    <div
        data-guide="topology.canvas"
        x-data="topologyGraph({
            graph: @js($graph),
            layout: @js($layout),
            iconSize: {{ $topologyIconSize }},
            restore: @js($restore ?? null),
        })"
        x-init="init($el)"
        wire:ignore
        class="flex-1 relative bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden"
    >
        <div x-ref="cy" class="absolute inset-0"></div>

        {{-- Toggle filtri: visibile solo a schermo intero, ancorato in alto a
             sinistra dentro la mappa. filters vive nel wrapper esterno → evento window. --}}
        <button
            type="button"
            x-show="fs"
            @click="$dispatch('topology-toggle-filters')"
            :class="filters ? 'bg-indigo-50 border-indigo-300 text-indigo-700' : 'bg-white text-gray-700 border-gray-200'"
            class="absolute top-2 left-2 z-20 rounded border shadow px-3 py-1.5 text-xs inline-flex items-center gap-1"
            style="display: none;"
        >
            <span>{{ __('topology.filters_and_grouping') }}</span>
            <span x-text="filters ? '▴' : '▾'"></span>
        </button>
        {{-- id + cytoscape-navigator class: the extension only reuses an existing
             element when `container` is a string selector (a DOM element makes it
             build its own panel on <body>). The cytoscape-navigator class lets the
             package's child styles (img/canvas/view) apply; topology-navigator is
             our size/position override. --}}
        <div x-ref="navigator" id="topology-navigator" class="cytoscape-navigator topology-navigator" style="display: none;"></div>

        <div
            class="absolute top-2 right-2 z-10 bg-white/95 border border-gray-200 rounded shadow px-3 py-2 text-xs flex items-center gap-2 select-none"
        >
            <button
                type="button"
                @click="toggleNavigator()"
                :class="showNavigator ? 'bg-indigo-50 border-indigo-300 text-indigo-700' : 'bg-white border-gray-200 text-gray-600'"
                class="rounded border shadow-sm px-2 py-1 text-xs whitespace-nowrap"
                title="{{ __('topology.minimap_title') }}"
            >{{ __('topology.minimap') }}</button>

            {{-- Toggle schermo intero. fs vive nel wrapper esterno: usiamo un
                 evento window per non creare una proprietà ombra in questo scope. --}}
            <button
                type="button"
                @click="$dispatch('topology-toggle-fs')"
                :class="fs ? 'bg-indigo-50 border-indigo-300 text-indigo-700' : 'bg-white border-gray-200 text-gray-600'"
                class="rounded border shadow-sm px-2 py-1 text-xs whitespace-nowrap"
                :title="fs ? @js(__('topology.fullscreen_exit')) : @js(__('topology.fullscreen'))"
                x-text="fs ? @js(__('topology.fullscreen_btn_exit')) : @js(__('topology.fullscreen_btn'))"
            ></button>

            @if ($canEdit)
                <span class="text-gray-600 pointer-events-none">{{ __('topology.icon_size') }}</span>
                <input
                    type="range"
                    min="{{ $minIconPx }}" max="{{ $maxIconPx }}" step="1"
                    x-model.number="globalIconSize"
                    @input="applyGlobalSize($event.target.value)"
                    @change="persistGlobalSize()"
                    @pointerdown.stop
                    class="w-40 cursor-pointer"
                />
                <span class="font-mono text-gray-700 pointer-events-none" x-text="globalIconSize + ' px'"></span>
            @endif
        </div>

        {{-- Context menu (right-click on a node). Positioned absolutely
             inside the cy container at click coordinates; coordinates are
             container-local (renderedPosition) so transforms compose
             naturally with pan/zoom changes after opening. --}}
        <div
            x-show="contextMenu.open"
            x-cloak
            :style="{ left: contextMenu.x + 'px', top: contextMenu.y + 'px' }"
            class="absolute z-30 min-w-[16rem] max-w-[22rem] rounded-md bg-white shadow-lg ring-1 ring-black/10 text-sm select-none"
            @click.outside="closeContextMenu()"
        >
            <div class="px-3 py-2 border-b text-xs font-semibold text-gray-700 truncate" x-text="contextMenu.nodeName || @js(__('topology.device_fallback'))"></div>

            {{-- Root view --}}
            <div x-show="contextMenu.view === 'root'">
                {{-- "Nome" è l'unica voce sensata per i nodi sintetici Wi-Fi
                     (non hanno porte fisiche né flag hidden_in_topology). --}}
                <button type="button" @click="openNamePositionView()" class="w-full text-left px-3 py-2 hover:bg-gray-50 flex items-center justify-between">
                    <span>{{ __('topology.menu_name') }}</span>
                    <span class="text-gray-400">▸</span>
                </button>
                <template x-if="contextMenu.nodeKind === 'equipment'">
                    <div>
                        <button type="button" @click="openPortsView()" class="w-full text-left px-3 py-2 hover:bg-gray-50 flex items-center justify-between">
                            <span>{{ __('topology.menu_ports') }}</span>
                            <span class="text-gray-400">▸</span>
                        </button>
                        <button type="button" @click="openHideView()" class="w-full text-left px-3 py-2 hover:bg-gray-50 flex items-center justify-between">
                            <span>{{ __('topology.menu_hide') }}</span>
                            <span class="text-gray-400">▸</span>
                        </button>
                    </div>
                </template>
                <template x-if="contextMenu.nodeKind === 'vpn' && (contextMenu.nodeVpnKind === 'remote' || contextMenu.nodeVpnKind === 'site')">
                    <button type="button" @click="openVpnDetailsView()" class="w-full text-left px-3 py-2 hover:bg-gray-50 flex items-center justify-between">
                        <span>{{ __('topology.menu_network_details') }}</span>
                        <span class="text-gray-400">▸</span>
                    </button>
                </template>
            </div>

            {{-- VPN details view: per-vpnKind toggles (remote-access vs site-to-site) --}}
            <div x-show="contextMenu.view === 'vpn-details'">
                <button type="button" @click="backToRoot()" class="w-full text-left px-3 py-1.5 text-xs text-indigo-600 hover:bg-gray-50">{{ __('common.back_arrow') }}</button>
                <div class="px-3 py-2 border-t border-b text-xs font-semibold text-gray-600">{{ __('topology.show_under_name') }}</div>
                <template x-if="contextMenu.nodeVpnKind === 'remote'">
                    <div>
                        <label class="flex items-center justify-between gap-2 px-3 py-2 hover:bg-gray-50 text-xs cursor-pointer">
                            <span>{{ __('topology.detail_routing') }}</span>
                            <input type="checkbox" :checked="isVpnDetailOn('routing')" @change="toggleVpnDetail('routing')" class="rounded border-gray-300" />
                        </label>
                        <label class="flex items-center justify-between gap-2 px-3 py-2 hover:bg-gray-50 text-xs cursor-pointer">
                            <span>{{ __('topology.detail_cidr') }}</span>
                            <input type="checkbox" :checked="isVpnDetailOn('cidr')" @change="toggleVpnDetail('cidr')" class="rounded border-gray-300" />
                        </label>
                    </div>
                </template>
                <template x-if="contextMenu.nodeVpnKind === 'site'">
                    <div>
                        <label class="flex items-center justify-between gap-2 px-3 py-2 hover:bg-gray-50 text-xs cursor-pointer">
                            <span>{{ __('topology.detail_networks_a') }}</span>
                            <input type="checkbox" :checked="isVpnDetailOn('netA')" @change="toggleVpnDetail('netA')" class="rounded border-gray-300" />
                        </label>
                        <label class="flex items-center justify-between gap-2 px-3 py-2 hover:bg-gray-50 text-xs cursor-pointer">
                            <span>{{ __('topology.detail_networks_b') }}</span>
                            <input type="checkbox" :checked="isVpnDetailOn('netB')" @change="toggleVpnDetail('netB')" class="rounded border-gray-300" />
                        </label>
                    </div>
                </template>
            </div>

            {{-- Hide / Show view --}}
            <div x-show="contextMenu.view === 'hide'">
                <button type="button" @click="backToRoot()" class="w-full text-left px-3 py-1.5 text-xs text-indigo-600 hover:bg-gray-50">{{ __('common.back_arrow') }}</button>
                {{-- Default: device is currently visible and not hidden by anything → offer hide options. --}}
                <template x-if="!contextMenu.nodeIsHiddenDb && !contextMenu.nodeIsHiddenSession">
                    <div class="p-2 grid grid-cols-1 gap-1">
                        <button type="button" @click="hideNodeSessionOnly()" class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs text-left">{{ __('topology.hide_session') }}</button>
                        <button type="button" @click="hideNodeAlways()" class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs text-left">{{ __('topology.hide_always') }}</button>
                    </div>
                </template>
                {{-- Session-only hide: shown only when "Includi nascosti" is on (otherwise the node isn't clickable). --}}
                <template x-if="contextMenu.nodeIsHiddenSession">
                    <div class="p-2 space-y-2">
                        <p class="text-[11px] text-gray-500 italic">{{ __('topology.hidden_session_note') }}</p>
                        <button type="button" @click="unhideNodeSessionOnly()" class="w-full px-2 py-1 rounded bg-emerald-600 text-white text-xs text-left">{{ __('topology.unhide_session') }}</button>
                    </div>
                </template>
                {{-- Persistent DB-flag hide: also shown only when "Includi nascosti" is on. --}}
                <template x-if="contextMenu.nodeIsHiddenDb">
                    <div class="p-2 space-y-2">
                        <p class="text-[11px] text-gray-500 italic">{{ __('topology.hidden_flag_note') }}</p>
                        <button type="button" @click="showNodeAlways()" class="w-full px-2 py-1 rounded bg-indigo-600 text-white text-xs text-left">{{ __('topology.show_always') }}</button>
                    </div>
                </template>
            </div>

            {{-- Name position view --}}
            <div x-show="contextMenu.view === 'name-position'">
                <button type="button" @click="backToRoot()" class="w-full text-left px-3 py-1.5 text-xs text-indigo-600 hover:bg-gray-50">{{ __('common.back_arrow') }}</button>
                <div class="px-3 py-2 border-t border-b text-xs font-semibold text-gray-600">{{ __('topology.label_position') }}</div>
                <div class="grid grid-cols-2 gap-1 p-2">
                    <template x-for="opt in [
                        { key: 'top', label: @js(__('topology.pos_top')) },
                        { key: 'bottom', label: @js(__('topology.pos_bottom')) },
                        { key: 'left', label: @js(__('topology.pos_left')) },
                        { key: 'right', label: @js(__('topology.pos_right')) },
                    ]" :key="opt.key">
                        <button type="button" @click="setNodeLabelPosition(opt.key)"
                            :class="currentNodeLabelPosition() === opt.key ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="px-2 py-1 rounded text-xs font-medium"
                            x-text="opt.label"></button>
                    </template>
                </div>
            </div>

            {{-- Ports list view --}}
            <div x-show="contextMenu.view === 'ports'">
                <button type="button" @click="backToRoot()" class="w-full text-left px-3 py-1.5 text-xs text-indigo-600 hover:bg-gray-50">{{ __('common.back_arrow') }}</button>
                <div class="max-h-72 overflow-y-auto border-t">
                    <div x-show="contextMenu.loading" class="px-3 py-2 text-xs text-gray-500 italic">{{ __('topology.loading_ports') }}</div>
                    <template x-for="iface in (contextMenu.interfaces || [])" :key="iface.id">
                        <button type="button" @click="openPortDetail(iface.id)"
                            class="w-full text-left px-3 py-1.5 hover:bg-gray-50 flex items-center justify-between text-xs">
                            <span class="font-mono" x-text="iface.name"></span>
                            <span class="text-gray-400">▸</span>
                        </button>
                    </template>
                    <div x-show="!contextMenu.loading && (contextMenu.interfaces || []).length === 0" class="px-3 py-2 text-xs text-gray-500 italic">{{ __('topology.no_interfaces') }}</div>
                </div>
            </div>

            {{-- Port detail view --}}
            <div x-show="contextMenu.view === 'port-detail'">
                <button type="button" @click="backToPorts()" class="w-full text-left px-3 py-1.5 text-xs text-indigo-600 hover:bg-gray-50">{{ __('common.back_arrow') }}</button>
                <div class="px-3 py-2 border-t border-b text-xs font-semibold text-gray-600 font-mono"
                     x-text="(contextMenu.interfaces || []).find(i => i.id === contextMenu.currentInterfaceId)?.name || ''"></div>
                <div class="px-3 py-2 space-y-1.5">
                    <label class="flex items-center gap-2 text-xs cursor-pointer">
                        <input type="checkbox" class="rounded border-gray-300 text-indigo-600"
                            :checked="isPortAttrOn(contextMenu.currentInterfaceId, 'ip')"
                            @change="togglePortAttr(contextMenu.currentInterfaceId, 'ip')" />
                        {{ __('topology.attr_ip') }}
                    </label>
                    <label class="flex items-center gap-2 text-xs cursor-pointer">
                        <input type="checkbox" class="rounded border-gray-300 text-indigo-600"
                            :checked="isPortAttrOn(contextMenu.currentInterfaceId, 'mac')"
                            @change="togglePortAttr(contextMenu.currentInterfaceId, 'mac')" />
                        MAC
                    </label>
                    <label class="flex items-center gap-2 text-xs cursor-pointer">
                        <input type="checkbox" class="rounded border-gray-300 text-indigo-600"
                            :checked="isPortAttrOn(contextMenu.currentInterfaceId, 'vlan')"
                            @change="togglePortAttr(contextMenu.currentInterfaceId, 'vlan')" />
                        {{ __('topology.attr_vlan') }}
                    </label>
                    <label class="flex items-center gap-2 text-xs cursor-pointer">
                        <input type="checkbox" class="rounded border-gray-300 text-indigo-600"
                            :checked="isPortAttrOn(contextMenu.currentInterfaceId, 'description')"
                            @change="togglePortAttr(contextMenu.currentInterfaceId, 'description')" />
                        {{ __('common.description') }}
                    </label>
                </div>
            </div>
        </div>
    </div>

    <livewire:equipment.drawer />
</div>
