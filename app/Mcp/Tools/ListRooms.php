<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListRooms extends TramaTool
{
    protected string $name = 'list_rooms';

    protected string $description = 'List the rooms (technical rooms, closets) of a site.';

    protected string $ability = 'rooms:read';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site id from list_sites.')->required(),
        ];
    }

    protected function execute(Request $request): array
    {
        $data = $request->validate(['site_id' => 'required|integer']);

        return $this->api('GET', "sites/{$data['site_id']}/rooms", $this->tenant($request));
    }
}
