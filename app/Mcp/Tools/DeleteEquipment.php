<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteEquipment extends TramaTool
{
    protected string $name = 'delete_equipment';

    protected string $description = 'Delete a device (soft delete: it stays in the audit log). Ask the user for confirmation first.';

    protected string $ability = 'equipment:delete';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'equipment_id' => $schema->integer()->required(),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        $request->validate(['equipment_id' => 'required|integer']);
        $this->api('DELETE', 'equipment/'.$request->get('equipment_id'), $tenant);

        return ['deleted' => true];
    }
}
