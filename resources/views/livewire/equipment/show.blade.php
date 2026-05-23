<div>
    <x-page-header :title="$equipment->name" :subtitle="($equipment->vendor ?? '') . ' ' . ($equipment->model ?? '')">
        <a href="{{ route('equipment.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-800">← Torna ai dispositivi</a>
        @can('update', $equipment)
            <a href="{{ route('equipment.index', ['edit' => $equipment->id]) }}" wire:navigate class="text-sm text-indigo-700 hover:text-indigo-900">✎ Modifica</a>
        @endcan
    </x-page-header>

    <div class="border-b border-gray-200 mb-6">
        <nav class="flex gap-x-4 text-sm">
            @php $tabs = ['general' => 'Generale', 'interfaces' => 'Interfacce', 'connections' => 'Connessioni', 'audit' => 'Audit']; @endphp
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
            <div><span class="text-gray-500">Tipo:</span> {{ $equipment->type?->label() }}</div>
            <div><span class="text-gray-500">Stato:</span> {{ $equipment->status?->label() }}</div>
            <div><span class="text-gray-500">Vendor:</span> {{ $equipment->vendor ?? '—' }}</div>
            <div><span class="text-gray-500">Modello:</span> {{ $equipment->model ?? '—' }}</div>
            <div><span class="text-gray-500">Seriale:</span> {{ $equipment->serial ?? '—' }}</div>
            <div><span class="text-gray-500">Firmware:</span> {{ $equipment->firmware ?? '—' }}</div>
            <div><span class="text-gray-500">Asset tag:</span> {{ $equipment->asset_tag ?? '—' }}</div>
            <div><span class="text-gray-500">Mgmt IP:</span> {{ $equipment->management_ip ?? '—' }}</div>
            @if ($equipment->rack)
                <div class="col-span-2"><span class="text-gray-500">Rack:</span>
                    <a href="{{ route('racks.show', $equipment->rack) }}" wire:navigate class="text-indigo-700 hover:underline">{{ $equipment->rack->name }}</a>
                    @if ($equipment->mounted) · U{{ $equipment->position_u_start }}–{{ $equipment->position_u_start + $equipment->position_u_height - 1 }}@endif
                    <span class="text-gray-400">— {{ $equipment->rack->room->name }} ({{ $equipment->rack->room->site->name }})</span>
                </div>
            @endif
            @if ($equipment->description)
                <div class="col-span-2"><span class="text-gray-500">Descrizione:</span><br>{{ $equipment->description }}</div>
            @endif
            @if ($equipment->tags->isNotEmpty())
                <div class="col-span-2"><span class="text-gray-500">Tag:</span> <x-tag-chips :tags="$equipment->tags" /></div>
            @endif
        </div>
    @endif

    @if ($activeTab === 'interfaces')
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-base font-semibold">{{ $isPatchLike ? 'Porte' : 'Interfacce' }} ({{ $interfaces->count() }})</h3>
            @can('create', App\Models\NetworkInterface::class)
                <button wire:click="openIfCreate" class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    <x-icon name="plus" class="h-4 w-4" /> {{ $isPatchLike ? 'Nuova porta' : 'Nuova interfaccia' }}
                </button>
            @endcan
        </div>

        @if ($isPatchLike)
            <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Porta</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Connessione front</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Connessione rear</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Connettore</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Azioni</th>
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
                                    if (! $other) return 'connessa';
                                    $sideLabel = $other->side?->value ? ' ('.$other->side->value.')' : '';
                                    return ($other->equipment?->name ?? '?') . ' · ' . ($other->name ?? '?') . $sideLabel;
                                };
                            @endphp
                            <tr wire:key="if-{{ $if->id }}">
                                <td class="px-4 py-3 font-mono text-gray-900">{{ $if->name }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $describe($frontConn, $if) }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $describe($rearConn, $rear) }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $if->connector ?? '—' }}</td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    @can('update', $if)
                                        <button wire:click="openIfEdit({{ $if->id }})" class="text-indigo-600 hover:text-indigo-800"><x-icon name="pencil" class="h-4 w-4 inline" /></button>
                                    @endcan
                                    @can('delete', $if)
                                        <button wire:click="deleteIf({{ $if->id }})" wire:confirm="Eliminare porta {{ $if->name }} (front + rear)?" class="text-red-600 hover:text-red-800"><x-icon name="trash" class="h-4 w-4 inline" /></button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Nessuna porta.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Speed</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">VLAN</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stato</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 text-sm">
                        @forelse ($interfaces as $if)
                            <tr wire:key="if-{{ $if->id }}">
                                <td class="px-4 py-3 font-mono text-gray-900">{{ $if->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $if->type?->value }} / {{ $if->media?->value }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $if->speed_mbps ? $if->speed_mbps.' Mbps' : '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $if->vlan_mode?->value }} {{ $if->vlan_default ? '· '.$if->vlan_default : '' }}</td>
                                <td class="px-4 py-3 font-mono text-gray-600">{{ $if->ip_address ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $if->status?->value }}</td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    @can('update', $if)
                                        <button wire:click="openIfEdit({{ $if->id }})" class="text-indigo-600 hover:text-indigo-800"><x-icon name="pencil" class="h-4 w-4 inline" /></button>
                                    @endcan
                                    @can('delete', $if)
                                        <button wire:click="deleteIf({{ $if->id }})" wire:confirm="Eliminare interfaccia {{ $if->name }}?" class="text-red-600 hover:text-red-800"><x-icon name="trash" class="h-4 w-4 inline" /></button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">Nessuna interfaccia.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    @if ($activeTab === 'connections')
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-base font-semibold">Connessioni ({{ $connections->count() }})</h3>
            @can('create', App\Models\Connection::class)
                <a href="{{ route('connections.create', ['from_equipment' => $equipment->id]) }}" wire:navigate class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    <x-icon name="plus" class="h-4 w-4" /> Nuova connessione
                </a>
            @endcan
        </div>

        <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Porta locale</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dispositivo remoto</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Porta remota</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cavo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Colore</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Etichetta</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tag</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stato</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Azioni</th>
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
                                    <a href="{{ route('connections.edit', ['connection' => $c, 'from_equipment' => $equipment->id]) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800 text-xs">Modifica</a>
                                @endcan
                                @can('delete', $c)
                                    <button wire:click="deleteConnection({{ $c->id }})" wire:confirm="Eliminare la connessione {{ $local?->name }} ↔ {{ $remote?->equipment?->name }} · {{ $remote?->name }}?" class="text-red-600 hover:text-red-800"><x-icon name="trash" class="h-4 w-4 inline" /></button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-6 text-center text-gray-500">Nessuna connessione per questo dispositivo.</td></tr>
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
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Evento</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Utente</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Diff</th>
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
                                    <summary class="cursor-pointer">Espandi</summary>
                                    <pre class="mt-1 max-h-48 overflow-auto bg-gray-50 p-2 rounded">{{ json_encode(['old' => $a->old_values, 'new' => $a->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Nessun audit registrato.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($showIfForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4 overflow-y-auto">
            <div class="bg-white rounded-md shadow-lg w-full max-w-2xl p-6 my-8">
                <h2 class="text-lg font-semibold mb-4">{{ $editingIfId ? 'Modifica interfaccia' : 'Nuova interfaccia' }}</h2>
                <form wire:submit="saveIf" class="space-y-3">
                    @if ($editingIfId === null)
                        <div class="bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/30 rounded-md p-3">
                            <label class="inline-flex items-center gap-x-2 text-sm font-medium text-indigo-900 dark:text-indigo-200">
                                <input type="checkbox" wire:model.live="ifBulk" class="rounded border-gray-300 text-indigo-600" />
                                Crea più interfacce in un colpo solo
                            </label>
                            @if ($ifBulk)
                                <div class="grid grid-cols-2 gap-3 mt-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-slate-300">Quantità</label>
                                        <input type="number" min="2" max="256" wire:model.live="ifBulkQuantity" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 shadow-sm text-sm" />
                                        @error('ifBulkQuantity')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-slate-300">Inizia da</label>
                                        <input type="number" min="0" max="9999" wire:model.live="ifBulkStartFrom" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 shadow-sm text-sm" />
                                        @error('ifBulkStartFrom')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                @php $preview = $this->previewBulkNames(); @endphp
                                @if ($preview !== [])
                                    <div class="text-xs text-indigo-900 dark:text-indigo-200 mt-2">
                                        Anteprima: <span class="font-mono">{{ implode(', ', $preview) }}</span>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endif
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700">{{ $ifBulk && $editingIfId === null ? 'Prefisso nome' : 'Nome' }}</label>
                            <input type="text" wire:model.live="ifName" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono" />
                            @error('ifName')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tipo</label>
                            <select wire:model="ifType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                @foreach (App\Enums\InterfaceType::cases() as $t) <option value="{{ $t->value }}">{{ $t->value }}</option> @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Media</label>
                            <select wire:model="ifMedia" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                @foreach (App\Enums\InterfaceMedia::cases() as $m) <option value="{{ $m->value }}">{{ $m->value }}</option> @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Connettore</label>
                            <select wire:model="ifConnector" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">—</option>
                                @foreach (['rj45', 'sc', 'lc', 'st', 'mpo', 'sfp', 'sfp+', 'qsfp'] as $c)
                                    <option value="{{ $c }}">{{ $c }}</option>
                                @endforeach
                            </select>
                            @error('ifConnector')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Speed (Mbps)</label>
                            <input type="number" wire:model="ifSpeedMbps" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">VLAN mode</label>
                            <select wire:model="ifVlanMode" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                @foreach (App\Enums\InterfaceVlanMode::cases() as $v) <option value="{{ $v->value }}">{{ $v->value }}</option> @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">VLAN default</label>
                            <input type="number" wire:model="ifVlanDefault" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">IP</label>
                            <input type="text" wire:model="ifIpAddress" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">MAC</label>
                            <input type="text" wire:model="ifMacAddress" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Stato</label>
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
                        <label class="block text-sm font-medium text-gray-700">Descrizione</label>
                        <input type="text" wire:model="ifDescription" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    @php
                        $bulkInvalid = $ifBulk && $editingIfId === null
                            && ($ifBulkQuantity === null || $ifBulkStartFrom === null);
                    @endphp
                    <div class="flex justify-end gap-x-2 pt-2">
                        <button type="button" wire:click="$set('showIfForm', false)" class="px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Annulla</button>
                        <button type="submit" @disabled($bulkInvalid) class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ $ifBulk && $editingIfId === null ? 'Crea '.($ifBulkQuantity ?? 0).' interfacce' : 'Salva' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
