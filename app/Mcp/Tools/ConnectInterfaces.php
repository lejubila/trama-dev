<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\ConnectionStatus;
use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

class ConnectInterfaces extends TramaTool
{
    protected string $name = 'connect_interfaces';

    protected string $description = 'Record a physical cable between two interfaces of different devices. Each interface can have only one active cable.';

    protected string $ability = 'connections:create';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'from_interface_id' => $schema->integer()->required(),
            'to_interface_id' => $schema->integer()->required(),
            'cable_type' => $schema->string()->description('e.g. cat6, cat6a, om4, os2, dac.')->required(),
            'cable_label' => $schema->string(),
            'cable_length_m' => $schema->number(),
            'color' => $schema->string(),
            'status' => $schema->string()->enum(ConnectionStatus::class),
            'notes' => $schema->string(),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        return $this->api('POST', 'connections', $tenant, $this->only($request, ['from_interface_id', 'to_interface_id', 'cable_type', 'cable_label', 'cable_length_m', 'color', 'status', 'notes']));
    }
}
