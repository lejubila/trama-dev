<div>
    @php $tenant = auth()->user()?->currentTenant; @endphp
    <x-page-header
        :title="$tenant ? 'Riepilogo · '.$tenant->name : 'Dashboard'"
        subtitle="Stato dell'infrastruttura del cliente attivo (cache 60s)"
    >
        @can('create', App\Models\Equipment::class)
            <button onclick="window.location.href='{{ route('equipment.index') }}'" class="text-sm text-gray-700 dark:text-slate-300 hover:underline">+ Dispositivo</button>
            <a href="{{ route('connections.create') }}" wire:navigate class="text-sm text-gray-700 dark:text-slate-300 hover:underline">+ Connessione</a>
        @endcan
        <a href="{{ route('topology.index') }}" wire:navigate class="text-sm text-indigo-700 dark:text-indigo-300 hover:underline">→ Topologia</a>
    </x-page-header>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        @foreach ([
            'Sedi' => $kpi['sites'],
            'Rack' => $kpi['racks'],
            'Dispositivi' => $kpi['equipment'],
            'Connessioni' => $kpi['connections_active'],
            'Interfacce up' => $kpi['interfaces_up_pct'].'%',
        ] as $label => $value)
            <div class="bg-white dark:bg-slate-800 shadow ring-1 ring-black ring-opacity-5 dark:ring-slate-600 rounded-md p-4">
                <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400">{{ $label }}</div>
                <div class="text-2xl font-semibold text-gray-900 dark:text-slate-100 mt-1">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 shadow ring-1 ring-black ring-opacity-5 dark:ring-slate-600 rounded-md p-4">
            <h2 class="text-base font-semibold text-gray-900 dark:text-slate-100 mb-3">Dispositivi per tipo</h2>
            @php
                $maxCount = max(1, max(array_values($kpi['by_type'] ?: [0])));
            @endphp
            @if (! $kpi['by_type'])
                <p class="text-sm text-gray-500 dark:text-slate-400">Nessun dispositivo.</p>
            @else
                <ul class="space-y-1">
                    @foreach ($types as $t)
                        @php $count = (int) ($kpi['by_type'][$t->value] ?? 0); @endphp
                        @if ($count > 0)
                            <li class="grid grid-cols-[8rem_1fr_3rem] items-center gap-x-3 text-xs">
                                <span class="text-gray-700 dark:text-slate-300">{{ $t->label() }}</span>
                                <div class="h-2 bg-gray-100 dark:bg-slate-700 rounded">
                                    <div class="h-2 bg-indigo-500 rounded" style="width: {{ round($count / $maxCount * 100) }}%"></div>
                                </div>
                                <span class="text-gray-700 dark:text-slate-300 text-right font-mono">{{ $count }}</span>
                            </li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="bg-white dark:bg-slate-800 shadow ring-1 ring-black ring-opacity-5 dark:ring-slate-600 rounded-md p-4">
            <h2 class="text-base font-semibold text-gray-900 dark:text-slate-100 mb-3">Salute</h2>
            @if ($kpi['unhealthy_equipment']->isEmpty() && $kpi['down_interfaces']->isEmpty())
                <p class="text-sm text-emerald-600 dark:text-emerald-400">Nessuna anomalia.</p>
            @else
                @if ($kpi['unhealthy_equipment']->isNotEmpty())
                    <h3 class="text-xs uppercase font-semibold text-gray-500 dark:text-slate-400 mt-2">Dispositivi non attivi</h3>
                    <ul class="text-sm space-y-0.5">
                        @foreach ($kpi['unhealthy_equipment'] as $eq)
                            <li><a href="{{ route('equipment.show', $eq) }}" wire:navigate class="text-indigo-700 dark:text-indigo-300 hover:underline">{{ $eq->name }}</a> <span class="text-xs text-gray-500 dark:text-slate-400">({{ $eq->status }})</span></li>
                        @endforeach
                    </ul>
                @endif
                @if ($kpi['down_interfaces']->isNotEmpty())
                    <h3 class="text-xs uppercase font-semibold text-gray-500 dark:text-slate-400 mt-3">Interfacce down</h3>
                    <ul class="text-sm space-y-0.5">
                        @foreach ($kpi['down_interfaces'] as $if)
                            <li>
                                <span class="font-mono">{{ $if->name }}</span>
                                <span class="text-xs text-gray-500 dark:text-slate-400">su {{ $if->equipment?->name ?? '?' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-slate-800 shadow ring-1 ring-black ring-opacity-5 dark:ring-slate-600 rounded-md p-4">
            <h2 class="text-base font-semibold text-gray-900 dark:text-slate-100 mb-3">Mappa sedi</h2>
            @if (count($kpi['sites_summary']) === 0)
                <p class="text-sm text-gray-500 dark:text-slate-400">Nessuna sede.</p>
            @else
                <ul class="text-sm space-y-1">
                    @foreach ($kpi['sites_summary'] as $s)
                        <li>
                            📍 <a href="{{ route('sites.show', $s['id']) }}" wire:navigate class="text-indigo-700 dark:text-indigo-300 hover:underline">{{ $s['name'] }}</a>
                            <span class="text-xs text-gray-500 dark:text-slate-400">{{ $s['address'] ?? '' }} · {{ $s['rooms_count'] }} locali</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="bg-white dark:bg-slate-800 shadow ring-1 ring-black ring-opacity-5 dark:ring-slate-600 rounded-md p-4">
            <h2 class="text-base font-semibold text-gray-900 dark:text-slate-100 mb-3">Ultime modifiche</h2>
            @if ($recentAudits->isEmpty())
                <p class="text-sm text-gray-500 dark:text-slate-400">Nessuna modifica recente.</p>
            @else
                <ul class="text-sm space-y-1">
                    @foreach ($recentAudits as $a)
                        <li class="text-gray-700 dark:text-slate-300">
                            <span class="text-xs text-gray-500 dark:text-slate-400">{{ $a->created_at?->diffForHumans() }}</span>
                            · <span class="text-xs">{{ $a->event }}</span>
                            · <span>{{ class_basename($a->auditable_type) }}#{{ $a->auditable_id }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-3 text-right">
                    <a href="{{ route('audit.index') }}" wire:navigate class="text-xs text-indigo-700 dark:text-indigo-300 hover:underline">Vedi tutto →</a>
                </div>
            @endif
        </div>
    </div>
</div>
