<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Validation\ValidationException;

/**
 * Runs an internal request against the REST API (/api/v1) with the caller's
 * own bearer token, so MCP tools get exactly the same abilities, tenant
 * allow-list, policies, validation and audit as the API — no duplicated rules.
 */
class ApiBridge
{
    public function __construct(private readonly HttpKernel $kernel) {}

    /**
     * @param  array<string, mixed>  $data  Query string for GET/DELETE, JSON body otherwise.
     * @return array<string, mixed> Decoded JSON (empty for 204).
     *
     * @throws ValidationException on 422, with the API's field errors
     * @throws ToolError on any other non-2xx answer
     */
    public function call(string $method, string $path, ?int $tenantId = null, array $data = []): array
    {
        $original = request();
        $method = strtoupper($method);
        $hasBody = ! in_array($method, ['GET', 'DELETE'], true);

        $server = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$original->bearerToken(),
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => $original->ip(),
        ];
        if ($tenantId !== null) {
            $server['HTTP_X_TENANT_ID'] = (string) $tenantId;
        }

        $sub = Request::create(
            $original->getSchemeAndHttpHost().'/api/v1/'.ltrim($path, '/'),
            $method,
            $hasBody ? [] : array_filter($data, fn ($v): bool => $v !== null && $v !== ''),
            [],
            [],
            $server,
            $hasBody ? (string) json_encode($data) : null,
        );

        $tenantBefore = TenantContext::id();
        auth()->forgetGuards();

        try {
            $response = $this->kernel->handle($sub);
        } finally {
            // Put the outer (MCP) request back everywhere the sub-request replaced it.
            app()->instance('request', $original);
            Facade::clearResolvedInstance('request');
            app('url')->setRequest($original);
            auth()->forgetGuards();
            TenantContext::setId($tenantBefore);
        }

        $status = $response->getStatusCode();
        $json = json_decode((string) $response->getContent(), true);
        $json = is_array($json) ? $json : [];

        if ($status >= 200 && $status < 300) {
            return $json;
        }

        if ($status === 422) {
            /** @var array<string, array<int, string>|string> $errors */
            $errors = $json['errors'] ?? ['request' => [$json['message'] ?? 'Invalid data']];

            throw ValidationException::withMessages($errors);
        }

        throw new ToolError(match ($status) {
            404 => 'Not found in this customer (wrong id or tenant_id?).',
            429 => 'Rate limit exceeded, retry in a minute.',
            default => (string) ($json['error'] ?? $json['message'] ?? "Request failed with HTTP {$status}."),
        });
    }

    /**
     * Flattens JSON:API-ish resources ({id, type, attributes, relationships,
     * links}) into plain objects a model reads easily.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function simplify(array $payload): array
    {
        $out = [];
        if (array_key_exists('data', $payload)) {
            $out['data'] = self::flatten($payload['data']);
        }
        if (isset($payload['meta']['total'])) {
            $out['pagination'] = [
                'page' => $payload['meta']['current_page'] ?? 1,
                'last_page' => $payload['meta']['last_page'] ?? 1,
                'total' => $payload['meta']['total'],
            ];
        }

        return $out;
    }

    private static function flatten(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::flatten(...), $value);
        }

        if (isset($value['attributes']) && is_array($value['attributes'])) {
            // Timestamps are noise for a model reading the network.
            $flat = ['id' => $value['id'] ?? null, ...array_diff_key($value['attributes'], ['created_at' => 1, 'updated_at' => 1])];
            foreach ((array) ($value['relationships'] ?? []) as $name => $rel) {
                $flat[$name] = self::flatten($rel);
            }

            return $flat;
        }

        unset($value['links']);

        return array_map(self::flatten(...), $value);
    }
}
