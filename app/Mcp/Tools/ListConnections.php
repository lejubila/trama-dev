<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\ConnectionStatus;
use App\Mcp\ToolError;
use App\Mcp\TramaTool;
use App\Models\Connection;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListConnections extends TramaTool
{
    protected string $name = 'list_connections';

    protected string $description = 'List physical cables between interfaces, with both ends (device and port names). Filter by device to see everything plugged into it.';

    protected string $ability = 'connections:read';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'equipment_id' => $schema->integer()->description('Only cables with one end on this device.'),
            'status' => $schema->string()->enum(ConnectionStatus::class),
            'limit' => $schema->integer()->min(1)->max(500)->description('Default 200.'),
        ];
    }

    protected function execute(Request $request): array
    {
        $data = $request->validate([
            'equipment_id' => 'nullable|integer',
            'status' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:500',
        ]);
        $this->tenant($request);
        if (! auth()->user()?->can('viewAny', Connection::class)) {
            throw new ToolError('Not allowed to read connections.');
        }

        $eq = $data['equipment_id'] ?? null;

        return ['data' => Connection::query()
            ->with(['fromInterface.equipment:id,name', 'toInterface.equipment:id,name'])
            ->when($eq, fn ($q) => $q->where(fn ($qq) => $qq
                ->whereHas('fromInterface', fn ($i) => $i->where('equipment_id', $eq))
                ->orWhereHas('toInterface', fn ($i) => $i->where('equipment_id', $eq))))
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('id')
            ->limit((int) ($data['limit'] ?? 200))
            ->get()
            ->map(fn (Connection $c): array => [
                'id' => $c->id,
                'from' => ['interface_id' => $c->from_interface_id, 'interface' => $c->fromInterface?->name, 'equipment_id' => $c->fromInterface?->equipment_id, 'equipment' => $c->fromInterface?->equipment?->name],
                'to' => ['interface_id' => $c->to_interface_id, 'interface' => $c->toInterface?->name, 'equipment_id' => $c->toInterface?->equipment_id, 'equipment' => $c->toInterface?->equipment?->name],
                'cable_type' => $c->cable_type,
                'cable_label' => $c->cable_label,
                'length_m' => $c->cable_length_m,
                'status' => $c->status?->value,
            ])
            ->all()];
    }
}
