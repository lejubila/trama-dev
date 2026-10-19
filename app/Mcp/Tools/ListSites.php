<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListSites extends TramaTool
{
    protected string $name = 'list_sites';

    protected string $description = 'List the sites (physical locations) of a customer.';

    protected string $ability = 'sites:read';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Optional filter on the site name.'),
            'page' => $schema->integer()->min(1),
        ];
    }

    protected function execute(Request $request): array
    {
        return $this->api('GET', 'sites', $this->tenant($request), [
            'q' => $request->get('query'),
            'page' => $request->get('page'),
            'per_page' => 100,
        ]);
    }
}
