<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListRacks extends TramaTool
{
    protected string $name = 'list_racks';

    protected string $description = 'List the racks of a room.';

    protected string $ability = 'racks:read';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'room_id' => $schema->integer()->description('Room id from list_rooms.')->required(),
        ];
    }

    protected function execute(Request $request): array
    {
        $data = $request->validate(['room_id' => 'required|integer']);

        return $this->api('GET', "rooms/{$data['room_id']}/racks", $this->tenant($request));
    }
}
