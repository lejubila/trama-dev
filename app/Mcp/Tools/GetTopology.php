<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\ToolError;
use App\Mcp\TramaTool;
use App\Models\Equipment;
use App\Services\TopologyService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetTopology extends TramaTool
{
    protected string $name = 'get_topology';

    protected string $description = 'Network topology of a customer as a compact graph: devices (with site/room/rack) and the links between them (ports, media, speed). Optional filters by site, VLAN and device types.';

    protected string $ability = 'topology:read';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer(),
            'vlan' => $schema->integer()->min(1)->max(4094)->description('Only devices and links carrying this VLAN.'),
            'types' => $schema->string()->description('Comma-separated device types, e.g. "switch,router,firewall".'),
        ];
    }

    protected function execute(Request $request): array
    {
        $data = $request->validate([
            'site_id' => 'nullable|integer',
            'vlan' => 'nullable|integer|min:1|max:4094',
            'types' => 'nullable|string',
        ]);
        $this->tenant($request);
        if (! auth()->user()?->can('viewAny', Equipment::class)) {
            throw new ToolError('Not allowed to read the topology.');
        }

        $types = array_values(array_filter(array_map('trim', explode(',', (string) ($data['types'] ?? '')))));

        // Grouping on, so every device carries its site / room / rack chain.
        $graph = app(TopologyService::class)->buildGraph(
            siteId: $data['site_id'] ?? null,
            types: $types !== [] ? $types : null,
            vlan: $data['vlan'] ?? null,
            groupByRack: true,
            groupBySite: true,
            groupByRoom: true,
        );

        $nodes = collect($graph['nodes'])->pluck('data');
        $containers = $nodes->whereNotNull('kind')->keyBy('id');
        $location = function (?string $parent) use ($containers): ?string {
            $path = [];
            while ($parent !== null && $containers->has($parent)) {
                // Site labels carry the address on a second line: keep the name only.
                array_unshift($path, strtok((string) ($containers[$parent]['label'] ?? $parent), "\n"));
                $parent = $containers[$parent]['parent'] ?? null;
            }

            return $path === [] ? null : implode(' / ', $path);
        };

        $devices = $nodes->whereNull('kind')->values();
        $names = $devices->pluck('label', 'id');

        return [
            'devices' => $devices->map(fn (array $n): array => array_filter([
                'id' => (int) substr((string) $n['id'], 3),
                'name' => $n['label'] ?? null,
                'type' => $n['type'] ?? null,
                'location' => $location($n['parent'] ?? null),
            ], fn ($v): bool => $v !== null))->all(),
            'links' => collect($graph['edges'])->pluck('data')->map(fn (array $e): array => array_filter([
                'from' => ($names[$e['source']] ?? $e['source']).(isset($e['fromIface']) ? " {$e['fromIface']}" : ''),
                'to' => ($names[$e['target']] ?? $e['target']).(isset($e['toIface']) ? " {$e['toIface']}" : ''),
                'media' => $e['media'] ?? null,
                'speed_mbps' => $e['speed'] ?? null,
                'status' => $e['status'] ?? null,
                'label' => $e['label'] ?? null,
            ], fn ($v): bool => $v !== null))->values()->all(),
        ];
    }
}
