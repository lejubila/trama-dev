<div>
    @if ($open && $equipment)
        <div
            class="fixed inset-0 z-40 flex justify-end bg-black/30"
            wire:click.self="close"
        >
            <aside class="w-full max-w-xl h-full bg-white shadow-xl overflow-y-auto">
                <div class="flex items-start justify-between p-4 border-b">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ $equipment->name }}</h2>
                        <p class="text-sm text-gray-500">{{ trim(($equipment->vendor ?? '').' '.($equipment->model ?? '')) }}</p>
                    </div>
                    <div class="flex items-center gap-x-2">
                        <a href="{{ route('equipment.show', $equipment) }}" wire:navigate class="text-xs text-indigo-700 hover:underline">Apri pagina →</a>
                        <button wire:click="close" class="text-gray-400 hover:text-gray-700 text-2xl leading-none px-2">×</button>
                    </div>
                </div>

                <div class="border-b border-gray-200 px-4">
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

                <div class="p-4 text-sm">
                    @if ($activeTab === 'general')
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-gray-700">
                            <div><dt class="text-gray-500 inline">Tipo:</dt> {{ $equipment->type?->label() }}</div>
                            <div><dt class="text-gray-500 inline">Stato:</dt> {{ $equipment->status?->label() }}</div>
                            <div><dt class="text-gray-500 inline">Vendor:</dt> {{ $equipment->vendor ?? '—' }}</div>
                            <div><dt class="text-gray-500 inline">Modello:</dt> {{ $equipment->model ?? '—' }}</div>
                            <div><dt class="text-gray-500 inline">Seriale:</dt> {{ $equipment->serial ?? '—' }}</div>
                            <div><dt class="text-gray-500 inline">Firmware:</dt> {{ $equipment->firmware ?? '—' }}</div>
                            <div><dt class="text-gray-500 inline">Asset tag:</dt> {{ $equipment->asset_tag ?? '—' }}</div>
                            <div><dt class="text-gray-500 inline">Mgmt IP:</dt> {{ $equipment->management_ip ?? '—' }}</div>
                            @if ($equipment->mounted)
                                <div class="col-span-2"><dt class="text-gray-500 inline">Posizione:</dt> {{ $equipment->rack?->name }} · U{{ $equipment->position_u_start }}–{{ $equipment->position_u_start + $equipment->position_u_height - 1 }} {{ $equipment->locked ? '🔒' : '' }}</div>
                            @endif
                            @php $roomName = $equipment->rack?->room?->name ?? $equipment->room?->name; @endphp
                            @if ($roomName)
                                <div class="col-span-2"><dt class="text-gray-500 inline">Locale:</dt> {{ $roomName }}</div>
                            @endif
                            @if ($equipment->description)
                                <div class="col-span-2 mt-2"><dt class="text-gray-500 mb-1">Descrizione:</dt><dd class="whitespace-pre-line">{{ $equipment->description }}</dd></div>
                            @endif
                            @if ($equipment->tags->isNotEmpty())
                                <div class="col-span-2 mt-2"><dt class="text-gray-500 inline">Tag:</dt> <x-tag-chips :tags="$equipment->tags" /></div>
                            @endif
                        </dl>
                    @elseif ($activeTab === 'interfaces')
                        @if ($equipment->interfaces->isEmpty())
                            <p class="text-gray-500">Nessuna interfaccia.</p>
                        @else
                            <ul class="divide-y divide-gray-200">
                                @foreach ($equipment->interfaces as $if)
                                    <li wire:key="d-if-{{ $if->id }}" class="py-2 flex items-center justify-between">
                                        <div>
                                            <div class="font-mono">{{ $if->name }}</div>
                                            <div class="text-xs text-gray-500">{{ $if->type?->value }} · {{ $if->media?->value }} · {{ $if->status?->value }}</div>
                                        </div>
                                        <div class="font-mono text-xs text-gray-600">{{ $if->ip_address ?? '' }}</div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @elseif ($activeTab === 'connections')
                        @if ($connections->isEmpty())
                            <p class="text-gray-500">Nessuna connessione.</p>
                        @else
                            <ul class="divide-y divide-gray-200">
                                @foreach ($connections as $c)
                                    @php
                                        $localIfId = $c->fromInterface->equipment_id === $equipment->id ? $c->fromInterface->id : $c->toInterface->id;
                                        $remote = $c->fromInterface->id === $localIfId ? $c->toInterface : $c->fromInterface;
                                        $local = $c->fromInterface->id === $localIfId ? $c->fromInterface : $c->toInterface;
                                    @endphp
                                    <li wire:key="d-cn-{{ $c->id }}" class="py-2">
                                        <div><span class="font-mono">{{ $local->name }}</span>
                                            <span class="text-gray-400">↔</span>
                                            <a href="{{ route('equipment.show', $remote->equipment) }}" wire:navigate class="text-indigo-700 hover:underline">{{ $remote->equipment->name }}</a>
                                            <span class="text-gray-400">·</span>
                                            <span class="font-mono">{{ $remote->name }}</span>
                                        </div>
                                        <div class="text-xs text-gray-500">{{ $c->cable_type }} {{ $c->cable_label ? '· '.$c->cable_label : '' }}</div>
                                        @if ($c->tags->isNotEmpty())
                                            <div class="mt-1"><x-tag-chips :tags="$c->tags" /></div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @else
                        @if ($audits->isEmpty())
                            <p class="text-gray-500">Nessun audit.</p>
                        @else
                            <ul class="divide-y divide-gray-200">
                                @foreach ($audits as $a)
                                    <li wire:key="d-a-{{ $a->id }}" class="py-2">
                                        <div class="text-xs text-gray-500">{{ $a->created_at?->format('Y-m-d H:i') }} · {{ $a->event }}</div>
                                        <details class="text-xs">
                                            <summary class="cursor-pointer text-gray-600">Diff</summary>
                                            <pre class="mt-1 max-h-40 overflow-auto bg-gray-50 p-2 rounded font-mono text-xs">{{ json_encode(['old' => $a->old_values, 'new' => $a->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </details>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </div>
            </aside>
        </div>
    @endif
</div>
