<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DisconnectInterfaces extends TramaTool
{
    protected string $name = 'disconnect_interfaces';

    protected string $description = 'Remove a cable (connection) between two interfaces. Ask the user for confirmation first.';

    protected string $ability = 'connections:delete';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'connection_id' => $schema->integer()->description('From get_equipment (connected_to.connection_id) or list_connections.')->required(),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        $request->validate(['connection_id' => 'required|integer']);
        $this->api('DELETE', 'connections/'.$request->get('connection_id'), $tenant);

        return ['deleted' => true];
    }
}
