<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

class CreateRack extends TramaTool
{
    protected string $name = 'create_rack';

    protected string $description = 'Create a rack inside a room.';

    protected string $ability = 'racks:create';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'room_id' => $schema->integer()->required(),
            'name' => $schema->string()->max(100)->required(),
            'height_units' => $schema->integer()->min(1)->max(60)->description('Height in U, typically 42.')->required(),
            'width_mm' => $schema->integer(),
            'depth_mm' => $schema->integer(),
            'numbering' => $schema->string()->enum(['bottom_up', 'top_down']),
            'notes' => $schema->string(),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        $data = $this->only($request, ['room_id', 'name', 'height_units', 'width_mm', 'depth_mm', 'numbering', 'notes']);
        $request->validate(['room_id' => 'required|integer']);

        return $this->api('POST', "rooms/{$data['room_id']}/racks", $tenant, $data);
    }
}
