<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListTenants extends TramaTool
{
    protected string $name = 'list_tenants';

    protected string $description = 'List the customers (tenants) this token can work on. Every other tool takes a tenant_id from this list.';

    protected string $ability = 'tenants:read';

    protected bool $tenantScoped = false;

    protected function arguments(JsonSchema $schema): array
    {
        return [];
    }

    protected function execute(Request $request): array
    {
        return $this->api('GET', 'tenants', null);
    }
}
