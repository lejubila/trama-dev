<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use App\Models\Equipment;
use App\Models\Rack;
use App\Services\RackPlacementService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetRack extends TramaTool
{
    protected string $name = 'get_rack';

    protected string $description = 'Rack details: size, the devices mounted in it with their U position (front/rear), and the free rack units on each side. Use it before placing a device.';

    protected string $ability = 'racks:read';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'rack_id' => $schema->integer()->required(),
        ];
    }

    protected function execute(Request $request): array
    {
        $data = $request->validate(['rack_id' => 'required|integer']);
        $tenant = $this->tenant($request);

        // Goes through the API first: ability, tenant and policy checks.
        $rack = $this->api('GET', "racks/{$data['rack_id']}", $tenant)['data'];

        /** @var Rack $model */
        $model = Rack::query()->findOrFail($data['rack_id']);
        $placement = app(RackPlacementService::class);
        $all = range(1, (int) $model->height_units);

        $rack['free_units'] = [
            'front' => array_values(array_diff($all, $placement->getOccupiedUnits($model, null, 'front'))),
            'rear' => array_values(array_diff($all, $placement->getOccupiedUnits($model, null, 'rear'))),
        ];

        if (auth()->user()?->tokenCan('equipment:read')) {
            $rack['devices'] = $model->equipment()
                ->orderByDesc('position_u_start')
                ->get()
                ->map(fn (Equipment $e): array => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'type' => $e->type?->value,
                    'mounted' => (bool) $e->mounted,
                    'u_start' => $e->position_u_start,
                    'u_height' => $e->position_u_height,
                    'side' => $e->position_orient ?? 'front',
                ])
                ->all();
        }

        return $rack;
    }
}
