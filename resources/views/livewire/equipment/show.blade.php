<div>
    <x-page-header :title="$equipment->name" :subtitle="($equipment->vendor ?? '') . ' ' . ($equipment->model ?? '')">
        <a href="{{ route('equipment.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-800">{{ __('equipment.back') }}</a>
        @can('update', $equipment)
            <a href="{{ route('equipment.index', ['edit' => $equipment->id]) }}" wire:navigate class="text-sm text-indigo-700 hover:text-indigo-900">{{ __('common.edit_link') }}</a>
        @endcan
    </x-page-header>

    <div class="border-b border-gray-200 mb-6">
        <nav class="flex gap-x-4 text-sm">
            @php
                $tabs = ['general' => __('common.general'), 'interfaces' => __('equipment.tab_interfaces'), 'connections' => __('equipment.tab_connections'), 'audit' => __('common.audit')];
                if ($isVirtualMachine) {
                    unset($tabs['connections']);
                }
            @endphp
            @foreach ($tabs as $key => $label)
                <button
                    wire:click="setTab('{{ $key }}')"
                    class="py-2 px-1 border-b-2 {{ $activeTab === $key ? 'border-indigo-600 text-indigo-700 font-medium' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                >{{ $label }}</button>
            @endforeach
        </nav>
    </div>

    @if ($activeTab === 'general')
        <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
            <div><span class="text-gray-500">{{ __('common.type') }}:</span> {{ $equipment->type?->label() }}</div>
            <div><span class="text-gray-500">{{ __('common.status') }}:</span> {{ $equipment->status?->label() }}</div>
            <div><span class="text-gray-500">Vendor:</span> {{ $equipment->vendor ?? '—' }}</div>
            <div><span class="text-gray-500">{{ __('common.model') }}:</span> {{ $equipment->model ?? '—' }}</div>
            <div><span class="text-gray-500">{{ __('equipment.label_serial') }}:</span> {{ $equipment->serial ?? '—' }}</div>
            <div><span class="text-gray-500">Firmware:</span> {{ $equipment->firmware ?? '—' }}</div>
            <div><span class="text-gray-500">{{ __('equipment.label_asset_tag') }}:</span> {{ $equipment->asset_tag ?? '—' }}</div>
            <div><span class="text-gray-500">Mgmt IP:</span> {{ $equipment->management_ip ?? '—' }}</div>
            @if ($equipment->rack)
                <div class="col-span-2"><span class="text-gray-500">Rack:</span>
                    <a href="{{ route('racks.show', $equipment->rack) }}" wire:navigate class="text-indigo-700 hover:underline">{{ $equipment->rack->name }}</a>
                    @if ($equipment->mounted) · U{{ $equipment->position_u_start }}–{{ $equipment->position_u_start + $equipment->position_u_height - 1 }}@endif
                    <span class="text-gray-400">— {{ $equipment->rack->room->name }} ({{ $equipment->rack->room->site->name }})</span>
                </div>
            @endif
            @if ($equipment->description)
                <div class="col-span-2"><span class="text-gray-500">{{ __('common.description') }}:</span><br>{{ $equipment->description }}</div>
            @endif
            @if ($equipment->tags->isNotEmpty())
                <div class="col-span-2"><span class="text-gray-500">{{ __('common.tags') }}:</span> <x-tag-chips :tags="$equipment->tags" /></div>
            @endif
        </div>
    @endif

    @if ($activeTab === 'interfaces')
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-base font-semibold">{{ $isPatchLike ? __('equipment.ports') : __('equipment.tab_interfaces') }} ({{ $interfaces->count() }})</h3>
            @can('create', App\Models\NetworkInterface::class)
                <button wire:click="openIfCreate" data-guide="interfaces.new" class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    <x-icon name="plus" class="h-4 w-4" /> {{ $isPatchLike ? __('equipment.new_port') : __('equipment.new_interface') }}
                </button>
            @endcan
        </div>

        @if ($isPatchLike)
            <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('equipment.col_port') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('equipment.col_conn_front') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('equipment.col_conn_rear') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('equipment.col_connector') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 text-sm">
                        @forelse ($interfaces as $if)
                            @php
                                $rear = $if->paired;
                                $frontConn = $if->activeConnection();
                                $rearConn = $rear?->activeConnection();
                                $describe = function ($conn, $localIf) {
                                    if (! $conn || ! $localIf) return '—';
                                    $other = $conn->otherEndpoint($localIf);
                                    if (! $other) return __('equipment.connected');
                                    $sideLabel = $other->side?->value ? ' ('.$other->side->value.')' : '';
                                    return ($other->equipment?->name ?? '?') . ' · ' . ($other->name ?? '?') . $sideLabel;
                                };
                            @endphp
                            @php
                                $connAction = function ($conn, $localIf) use ($equipment) {
                                    if (! $localIf) return null;
                                    if ($conn) {
                                        return [
                                            'label' => __('common.edit'),
                                            'href' => route('connections.edit', ['connection' => $conn, 'from_equipment' => $equipment->id]),
                                        ];
                                    }
                                    return [
                                        'label' => __('common.create'),
                                        'href' => route('connections.create', ['from_equipment' => $equipment->id, 'from_iface' => $localIf->id]),
                                    ];
                                };
                                $frontAction = $connAction($frontConn, $if);
                                $rearAction = $connAction($rearConn, $rear);
                            @endphp
                            <tr wire:key="if-{{ $if->id }}">
                                <td class="px-4 py-3 font-mono text-gray-900">{{ $if->name }}</td>
                                <td class="px-4 py-3 text-gray-700">
                                    <div>{{ $describe($frontConn, $if) }}</div>
                                    @if ($frontAction)
                                        @can('create', App\Models\Connection::class)
                                            <a href="{{ $frontAction['href'] }}" wire:navigate class="text-xs text-indigo-600 hover:underline">{{ $frontAction['label'] }}</a>
                                        @endcan
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    <div>{{ $describe($rearConn, $rear) }}</div>
                                    @if ($rearAction)
                                        @can('create', App\Models\Connection::class)
                                            <a href="{{ $rearAction['href'] }}" wire:navigate class="text-xs text-indigo-600 hover:underline">{{ $rearAction['label'] }}</a>
                                        @endcan
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $if->connector ?? '—' }}</td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    @can('update', $if)
                                        <button wire:click="openIfEdit({{ $if->id }})" class="text-indigo-600 hover:text-indigo-800"><x-icon name="pencil" class="h-4 w-4 inline" /></button>
                                    @endcan
                                    @can('delete', $if)
                                        <button wire:click="deleteIf({{ $if->id }})" wire:confirm="{{ __('equipment.delete_port_confirm', ['name' => $if->name]) }}" class="text-red-600 hover:text-red-800"><x-icon name="trash" class="h-4 w-4 inline" /></button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('equipment.no_ports') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('common.name') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('common.type') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('equipment.col_speed') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">VLAN</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('common.status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('equipment.col_connection') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 text-sm">
                        @forelse ($interfaces as $if)
                            @php
                                $conn = $connByIfaceId[$if->id] ?? null;
                                $remote = null;
                                if ($conn) {
                                    $remote = $conn->from_interface_id === $if->id ? $conn->toInterface : $conn->fromInterface;
                                }
                            @endphp
                            <tr wire:key="if-{{ $if->id }}">
                                <td class="px-4 py-3 font-mono text-gray-900">
                                    {{ $if->name }}
                                    @if ($if->backedBy)
                                        <div class="text-xs text-violet-700 font-normal mt-0.5">
                                            ← {{ __('equipment.via') }}
                                            @if ($if->backedBy->equipment)
                                                <a href="{{ route('equipment.show', $if->backedBy->equipment) }}" wire:navigate class="hover:underline">{{ $if->backedBy->equipment->name }}</a>
                                            @endif
                                            : <span class="font-mono">{{ $if->backedBy->name }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $if->type?->value }} / {{ $if->media?->value }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $if->speed_mbps ? $if->speed_mbps.' Mbps' : '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ $if->vlan_mode?->value }}@if ($if->vlan_default) · {{ $if->vlan_default }}@endif
                                    @if (is_array($if->vlans_allowed) && $if->vlans_allowed !== []
                                        && in_array($if->vlan_mode?->value, ['trunk', 'hybrid'], true))
                                        <span class="text-gray-500">({{ $this->formatVlanRanges($if->vlans_allowed) }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-gray-600">{{ $if->ip_address ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $if->status?->value }}</td>
                                <td class="px-4 py-3 text-gray-700">
                                    @if ($if->type?->value === 'virtual')
                                        @if ($if->backedBy)
                                            <span class="text-xs text-violet-700">
                                                {{ $isVirtualMachine ? 'vNIC' : 'sub-if' }} — {{ __('equipment.via') }}
                                                @if ($if->backedBy->equipment && $if->backedBy->equipment->id !== $equipment->id)
                                                    <a href="{{ route('equipment.show', $if->backedBy->equipment) }}" wire:navigate class="hover:underline">{{ $if->backedBy->equipment->name }}</a>
                                                    /
                                                @endif
                                                <span class="font-mono">{{ $if->backedBy->name }}</span>
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400" title="{{ __('equipment.virtual_no_conn_title') }}">{{ $isVirtualMachine ? 'vNIC' : 'sub-if' }} — {{ __('equipment.backing_missing') }}</span>
                                        @endif
                                    @elseif ($conn && $remote?->equipment)
                                        <a href="{{ route('equipment.show', $remote->equipment) }}" wire:navigate class="text-indigo-700 hover:underline">{{ $remote->equipment->name }}</a>
                                        · <span class="font-mono">{{ $remote->name }}</span>
                                        @can('update', $conn)
                                            <a href="{{ route('connections.edit', ['connection' => $conn, 'from_equipment' => $equipment->id]) }}" wire:navigate class="ml-1 text-xs text-indigo-600 hover:underline">{{ __('common.edit') }}</a>
                                        @endcan
                                    @else
                                        <span class="text-gray-400">—</span>
                                        @can('create', App\Models\Connection::class)
                                            <a href="{{ route('connections.create', ['from_equipment' => $equipment->id, 'from_iface' => $if->id]) }}" wire:navigate class="ml-1 text-xs text-indigo-600 hover:underline">{{ __('common.create') }}</a>
                                        @endcan
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    @can('update', $if)
                                        <button wire:click="openIfEdit({{ $if->id }})" class="text-indigo-600 hover:text-indigo-800"><x-icon name="pencil" class="h-4 w-4 inline" /></button>
                                    @endcan
                                    @can('delete', $if)
                                        <button wire:click="deleteIf({{ $if->id }})" wire:confirm="{{ __('equipment.delete_if_confirm', ['name' => $if->name]) }}" class="text-red-600 hover:text-red-800"><x-icon name="trash" class="h-4 w-4 inline" /></button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">{{ __('equipment.no_interfaces') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    @if ($activeTab === 'connections')
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-base font-semibold">{{ __('equipment.connections_heading', ['count' => $connections->count()]) }}</h3>
            @can('create', App\Models\Connection::class)
                <a href="{{ route('connections.create', ['from_equipment' => $equipment->id]) }}" wire:navigate class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    <x-icon name="plus" class="h-4 w-4" /> {{ __('equipment.new_connection') }}
                </a>
            @endcan
        </div>

        <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('equipment.col_local_port') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('equipment.col_remote_device') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('equipment.col_remote_port') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('equipment.col_cable') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('common.color') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('common.label') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('common.tags') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('common.status') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 text-sm">
                    @forelse ($connections as $c)
                        @php
                            $isFromHere = $c->fromInterface?->equipment_id === $equipment->id;
                            $local = $isFromHere ? $c->fromInterface : $c->toInterface;
                            $remote = $isFromHere ? $c->toInterface : $c->fromInterface;
                        @endphp
                        <tr wire:key="cn-{{ $c->id }}">
                            <td class="px-4 py-3 font-mono text-gray-900">{{ $local?->name ?? '—' }}@if ($local?->side) <span class="text-xs text-gray-500">({{ $local->side->value }})</span>@endif</td>
                            <td class="px-4 py-3">
                                @if ($remote?->equipment)
                                    <a href="{{ route('equipment.show', $remote->equipment) }}" wire:navigate class="text-indigo-700 hover:underline">{{ $remote->equipment->name }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-gray-700">{{ $remote?->name ?? '—' }}@if ($remote?->side) <span class="text-xs text-gray-500">({{ $remote->side->value }})</span>@endif</td>
                            <td class="px-4 py-3 text-gray-600">{{ $c->cable_type }} {{ $c->cable_length_m ? '· '.$c->cable_length_m.' m' : '' }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                @if ($c->color)
                                    <span class="inline-flex items-center gap-x-1.5">
                                        <span class="inline-block h-4 w-4 rounded border border-gray-300" style="background-color: {{ $c->color }}" title="{{ $c->color }}"></span>
                                        <span class="font-mono text-xs">{{ strtoupper($c->color) }}</span>
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $c->cable_label ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                @if ($c->tags->isNotEmpty())
                                    <x-tag-chips :tags="$c->tags" />
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $c->status?->value }}</td>
                            <td class="px-4 py-3 text-right space-x-2">
                                @can('update', $c)
                                    <a href="{{ route('connections.edit', ['connection' => $c, 'from_equipment' => $equipment->id]) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800 text-xs">{{ __('common.edit') }}</a>
                                @endcan
                                @can('delete', $c)
                                    <button wire:click="deleteConnection({{ $c->id }})" wire:confirm="{{ __('equipment.delete_conn_confirm', ['local' => $local?->name, 'remote' => $remote?->equipment?->name, 'port' => $remote?->name]) }}" class="text-red-600 hover:text-red-800"><x-icon name="trash" class="h-4 w-4 inline" /></button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-6 text-center text-gray-500">{{ __('equipment.no_connections') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($activeTab === 'audit')
        <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('common.date') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('common.event') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('common.user') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('common.diff') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 text-sm">
                    @forelse ($audits as $a)
                        <tr wire:key="audit-{{ $a->id }}">
                            <td class="px-4 py-3 text-gray-600">{{ $a->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700">{{ $a->event }}</span></td>
                            <td class="px-4 py-3 text-gray-600">{{ optional($a->user)->name ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600">
                                <details>
                                    <summary class="cursor-pointer">{{ __('common.expand') }}</summary>
                                    <pre class="mt-1 max-h-48 overflow-auto bg-gray-50 p-2 rounded">{{ json_encode(['old' => $a->old_values, 'new' => $a->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ __('common.no_audit') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($showIfForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4 overflow-y-auto">
            <div data-guide="interfaces.form" class="bg-white rounded-md shadow-lg w-full max-w-2xl p-6 my-8">
                <h2 class="text-lg font-semibold mb-4">{{ $editingIfId ? __('equipment.if_form_edit') : __('equipment.new_interface') }}</h2>
                <form wire:submit="saveIf" class="space-y-3">
                    @if ($editingIfId === null)
                        <div class="bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/30 rounded-md p-3">
                            <label class="inline-flex items-center gap-x-2 text-sm font-medium text-indigo-900 dark:text-indigo-200">
                                <input type="checkbox" wire:model.live="ifBulk" class="rounded border-gray-300 text-indigo-600" />
                                {{ __('equipment.bulk_toggle') }}
                            </label>
                            @if ($ifBulk)
                                <div class="grid grid-cols-2 gap-3 mt-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-slate-300">{{ __('equipment.bulk_quantity') }}</label>
                                        <input type="number" min="2" max="256" wire:model.live="ifBulkQuantity" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 shadow-sm text-sm" />
                                        @error('ifBulkQuantity')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-slate-300">{{ __('equipment.bulk_start') }}</label>
                                        <input type="number" min="0" max="9999" wire:model.live="ifBulkStartFrom" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 shadow-sm text-sm" />
                                        @error('ifBulkStartFrom')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                @php $preview = $this->previewBulkNames(); @endphp
                                @if ($preview !== [])
                                    <div class="text-xs text-indigo-900 dark:text-indigo-200 mt-2">
                                        {{ __('equipment.bulk_preview') }} <span class="font-mono">{{ implode(', ', $preview) }}</span>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endif
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700">{{ $ifBulk && $editingIfId === null ? __('equipment.bulk_name_label') : __('common.name') }}</label>
                            <input type="text" wire:model.live="ifName" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono" />
                            @if ($ifBulk && $editingIfId === null)
                                <p class="text-xs text-gray-500 mt-1">
                                    {!! __('equipment.bulk_name_help_html') !!}
                                </p>
                            @endif
                            @error('ifName')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('common.type') }}</label>
                            <select wire:model.live="ifType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                @foreach (App\Enums\InterfaceType::cases() as $t) <option value="{{ $t->value }}">{{ $t->value }}</option> @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('equipment.label_media') }}</label>
                            <select wire:model="ifMedia" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                @foreach (App\Enums\InterfaceMedia::cases() as $m) <option value="{{ $m->value }}">{{ $m->value }}</option> @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('equipment.col_connector') }}</label>
                            <select wire:model="ifConnector" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">—</option>
                                @foreach (['rj45', 'sc', 'lc', 'st', 'mpo', 'sfp', 'sfp+', 'qsfp'] as $c)
                                    <option value="{{ $c }}">{{ $c }}</option>
                                @endforeach
                            </select>
                            @error('ifConnector')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('equipment.label_speed') }}</label>
                            <input type="number" wire:model="ifSpeedMbps" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                        @unless ($this->isPassthroughEquipment())
                            @php
                                $vlanDisabled = in_array($ifVlanMode, ['none', 'transparent'], true);
                                $vlansAllowedDisabled = in_array($ifVlanMode, ['none', 'access', 'transparent'], true);
                            @endphp
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('equipment.label_vlan_mode') }}</label>
                                <select wire:model.live="ifVlanMode" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    @foreach (App\Enums\InterfaceVlanMode::cases() as $v) <option value="{{ $v->value }}">{{ $v->value }}</option> @endforeach
                                </select>
                                <p class="text-xs text-gray-500 mt-1">
                                    {!! __('equipment.vlan_mode_help_html') !!}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('equipment.label_vlan_default') }}</label>
                                <input type="number" wire:model="ifVlanDefault" @disabled($vlanDisabled) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100 disabled:text-gray-400" />
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700">{{ __('equipment.label_vlans_allowed') }}</label>
                                <input type="text" wire:model="ifVlansAllowedText" placeholder="{{ __('equipment.vlans_placeholder') }}" @disabled($vlansAllowedDisabled) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono disabled:bg-gray-100 disabled:text-gray-400" />
                                <p class="text-xs text-gray-500 mt-1">
                                    @if ($vlansAllowedDisabled)
                                        {!! __('equipment.vlans_not_applicable_html', ['mode' => e($ifVlanMode)]) !!}
                                    @else
                                        {!! __('equipment.vlans_help_html') !!}
                                    @endif
                                </p>
                                @error('ifVlansAllowedText')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">IP</label>
                                <input type="text" wire:model="ifIpAddress" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">MAC</label>
                                <input type="text" wire:model="ifMacAddress" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono" />
                            </div>
                        @endunless
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('common.status') }}</label>
                            <select wire:model="ifStatus" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                @foreach (App\Enums\InterfaceStatus::cases() as $s) <option value="{{ $s->value }}">{{ $s->value }}</option> @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">PoE</label>
                            <select wire:model="ifPoe" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                @foreach (App\Enums\InterfacePoe::cases() as $p) <option value="{{ $p->value }}">{{ $p->value }}</option> @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('common.description') }}</label>
                        <textarea wire:model="ifDescription" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                    </div>
                    @if ($ifType === 'virtual')
                        <div class="border-t pt-3">
                            <label class="block text-sm font-medium text-gray-700">
                                {{ $isVirtualMachine ? __('equipment.backing_vm_label') : __('equipment.backing_label') }}
                            </label>
                            <select wire:model="ifBackedById" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">{{ __('common.none_female_option') }}</option>
                                @foreach ($backingCandidates as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->type?->value }})</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ $isVirtualMachine ? __('equipment.backing_vm_help') : __('equipment.backing_help') }}
                            </p>
                            @error('ifBackedById')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    @php
                        $bulkInvalid = $ifBulk && $editingIfId === null
                            && ($ifBulkQuantity === null || $ifBulkStartFrom === null);
                    @endphp
                    <div class="flex justify-end gap-x-2 pt-2">
                        <button type="button" wire:click="$set('showIfForm', false)" class="px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">{{ __('common.cancel') }}</button>
                        <button type="submit" @disabled($bulkInvalid) class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ $ifBulk && $editingIfId === null ? __('equipment.bulk_submit', ['count' => $ifBulkQuantity ?? 0]) : __('common.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
