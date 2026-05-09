<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Enums\EquipmentType;
use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Rack;
use App\Models\Site;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;
use OwenIt\Auditing\Models\Audit;

#[Layout('layouts.app')]
class Index extends Component
{
    public function render(): View
    {
        $tenantId = TenantContext::id();
        $cacheKey = "dashboard.kpi.tenant.{$tenantId}";

        $kpi = Cache::remember($cacheKey, now()->addSeconds(60), function (): array {
            $totalIfaces = NetworkInterface::query()->count();
            $upIfaces = NetworkInterface::query()->where('status', 'up')->count();

            $byType = Equipment::query()
                ->selectRaw('type, count(*) as c')
                ->groupBy('type')
                ->pluck('c', 'type')
                ->all();

            return [
                'sites' => Site::query()->count(),
                'racks' => Rack::query()->count(),
                'equipment' => Equipment::query()->count(),
                'connections_active' => Connection::query()->where('status', 'active')->count(),
                'interfaces_total' => $totalIfaces,
                'interfaces_up_pct' => $totalIfaces > 0 ? round(($upIfaces / $totalIfaces) * 100, 1) : 0.0,
                'by_type' => $byType,
                'sites_summary' => Site::query()
                    ->withCount('rooms')
                    ->orderBy('name')
                    ->limit(8)
                    ->get(['id', 'name', 'address'])
                    ->map(fn (Site $s): array => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'address' => $s->address,
                        'rooms_count' => (int) $s->rooms_count,
                    ])
                    ->all(),
                'unhealthy_equipment' => Equipment::query()
                    ->whereIn('status', ['maintenance', 'inactive'])
                    ->limit(5)
                    ->get(['id', 'name', 'status']),
                'down_interfaces' => NetworkInterface::query()
                    ->where('status', 'down')
                    ->with('equipment:id,name')
                    ->limit(5)
                    ->get(['id', 'name', 'equipment_id']),
            ];
        });

        // Recent audits — small, cheap query, not worth caching
        $recentAudits = Audit::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->latest()
            ->limit(10)
            ->get();

        return view('livewire.dashboard.index', [
            'kpi' => $kpi,
            'recentAudits' => $recentAudits,
            'types' => EquipmentType::cases(),
        ]);
    }
}
