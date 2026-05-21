<?php

declare(strict_types=1);

namespace App\Livewire\Layout;

use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Site;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    public bool $open = false;

    /** Min chars before we hit the DB. */
    private const MIN_CHARS = 2;

    /** Max rows per group. */
    private const PER_GROUP = 5;

    public function updatedQuery(string $value): void
    {
        $this->open = strlen(trim($value)) >= self::MIN_CHARS;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function clear(): void
    {
        $this->query = '';
        $this->open = false;
    }

    public function render(): View
    {
        $q = trim($this->query);
        $needle = '%'.$q.'%';

        /** @var array<string, Collection<int, array<string, mixed>>> $groups */
        $groups = [
            'equipment' => collect([]),
            'interfaces' => collect([]),
            'sites' => collect([]),
            'connections' => collect([]),
        ];

        if (strlen($q) >= self::MIN_CHARS) {
            $groups['equipment'] = Equipment::query()
                ->where(function ($qq) use ($needle): void {
                    $qq->where('name', 'ilike', $needle)
                        ->orWhere('serial', 'ilike', $needle)
                        ->orWhere('asset_tag', 'ilike', $needle);
                })
                ->orderBy('name')
                ->limit(self::PER_GROUP)
                ->get(['id', 'name', 'vendor', 'model', 'serial'])
                ->map(fn ($e): array => [
                    'id' => $e->id,
                    'label' => $e->name,
                    'meta' => trim(($e->vendor ?? '').' '.($e->model ?? '').($e->serial ? ' · '.$e->serial : '')),
                    'url' => route('equipment.show', $e),
                ]);

            $groups['interfaces'] = NetworkInterface::query()
                ->with('equipment:id,name')
                ->where(function ($qq) use ($needle): void {
                    $qq->where('name', 'ilike', $needle)
                        ->orWhere('ip_address', 'ilike', $needle)
                        ->orWhere('mac_address', 'ilike', $needle);
                })
                ->orderBy('name')
                ->limit(self::PER_GROUP)
                ->get(['id', 'name', 'equipment_id', 'ip_address'])
                ->map(fn ($i): array => [
                    'id' => $i->id,
                    'label' => $i->name,
                    'meta' => ($i->equipment?->name ?: '?').($i->ip_address ? ' · '.$i->ip_address : ''),
                    'url' => $i->equipment ? route('equipment.show', $i->equipment) : '#',
                ]);

            $groups['sites'] = Site::query()
                ->where('name', 'ilike', $needle)
                ->orderBy('name')
                ->limit(self::PER_GROUP)
                ->get(['id', 'name', 'address'])
                ->map(fn ($s): array => [
                    'id' => $s->id,
                    'label' => $s->name,
                    'meta' => $s->address ?? '',
                    'url' => route('sites.show', $s),
                ]);

            $groups['connections'] = Connection::query()
                ->where('cable_label', 'ilike', $needle)
                ->orderBy('cable_label')
                ->limit(self::PER_GROUP)
                ->get(['id', 'cable_label', 'cable_type'])
                ->map(fn ($c): array => [
                    'id' => $c->id,
                    'label' => $c->cable_label ?? __('search.unlabeled'),
                    'meta' => $c->cable_type,
                    'url' => route('connections.index'),
                ]);
        }

        $totalHits = collect($groups)->sum(fn (Collection $g): int => $g->count());

        return view('livewire.layout.global-search', [
            'groups' => $groups,
            'totalHits' => $totalHits,
        ]);
    }
}
