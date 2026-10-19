<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

class CreateRoom extends TramaTool
{
    protected string $name = 'create_room';

    protected string $description = 'Create a room (technical room, closet) inside a site.';

    protected string $ability = 'rooms:create';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->required(),
            'name' => $schema->string()->max(150)->required(),
            'floor' => $schema->string(),
            'notes' => $schema->string(),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        $data = $this->only($request, ['site_id', 'name', 'floor', 'notes']);
        $request->validate(['site_id' => 'required|integer']);

        return $this->api('POST', "sites/{$data['site_id']}/rooms", $tenant, $data);
    }
}
