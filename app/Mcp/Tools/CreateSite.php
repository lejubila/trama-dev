<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

class CreateSite extends TramaTool
{
    protected string $name = 'create_site';

    protected string $description = 'Create a site (physical location) for the customer.';

    protected string $ability = 'sites:create';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->max(150)->required(),
            'address' => $schema->string(),
            'notes' => $schema->string(),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        return $this->api('POST', 'sites', $tenant, $this->only($request, ['name', 'address', 'notes']));
    }
}
