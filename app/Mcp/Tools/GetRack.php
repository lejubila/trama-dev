<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use App\Models\Equipment;
use App\Models\Rack;
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
        $devices = $model->equipment()->orderByDesc('position_u_start')->get();

        // On-top and unmounted devices occupy no U; NULL orient is front.
        $free = function (string $side) use ($devices, $model): array {
            $occupied = $devices
                ->filter(fn (Equipment $e): bool => $e->mounted && ! $e->on_top && $e->position_u_start !== null && $e->position_u_height !== null
                    && ($e->position_orient ?: 'front') === $side)
                ->flatMap(fn (Equipment $e): array => range($e->position_u_start, $e->position_u_start + $e->position_u_height - 1))
                ->all();

            return array_values(array_diff(range(1, (int) $model->height_units), $occupied));
        };

        $rack['free_units'] = ['front' => $free('front'), 'rear' => $free('rear')];

        if (auth()->user()?->tokenCan('equipment:read')) {
            $rack['devices'] = $devices
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
