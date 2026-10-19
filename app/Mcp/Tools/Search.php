<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use App\Services\GlobalSearchService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class Search extends TramaTool
{
    protected string $name = 'search';

    protected string $description = 'Search a customer by free text: devices (name, serial, asset tag), interfaces (name, IP address, MAC address), sites (name) and cables (label). Use it to find ids before reading or changing things.';

    protected string $ability = 'equipment:read';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Text to look for, at least 2 characters (e.g. "SW-CORE", "10.0.1.", "aa:bb").')->required(),
            'limit' => $schema->integer()->min(1)->max(50)->description('Max results per group (default 10).'),
        ];
    }

    protected function execute(Request $request): array
    {
        $data = $request->validate(['query' => 'required|string|min:2', 'limit' => 'nullable|integer|min:1|max:50']);
        $this->tenant($request);

        $groups = app(GlobalSearchService::class)->search($data['query'], (int) ($data['limit'] ?? 10));

        return collect($groups)->map(fn ($rows) => $rows->map(fn (array $r): array => array_diff_key($r, ['meta' => 1]))->values())->all();
    }
}
