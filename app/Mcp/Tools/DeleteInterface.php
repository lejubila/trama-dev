<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteInterface extends TramaTool
{
    protected string $name = 'delete_interface';

    protected string $description = 'Delete an interface (port). Ask the user for confirmation first.';

    protected string $ability = 'interfaces:delete';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'interface_id' => $schema->integer()->required(),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        $request->validate(['interface_id' => 'required|integer']);
        $this->api('DELETE', 'interfaces/'.$request->get('interface_id'), $tenant);

        return ['deleted' => true];
    }
}
