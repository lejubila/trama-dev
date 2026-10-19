<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Api\AccessibleTenants;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Base for Trama tools: visible only when the token holds $ability, with
 * helpers to pick the customer (tenant) and to call the REST API.
 */
abstract class TramaTool extends Tool
{
    /** Token ability required to see and use this tool, e.g. "equipment:read". */
    protected string $ability = '';

    /** Whether the tool works inside a customer and needs tenant_id. */
    protected bool $tenantScoped = true;

    public function __construct(protected ApiBridge $bridge) {}

    public function shouldRegister(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($this->ability === '' || $user->tokenCan($this->ability));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $fields = $this->arguments($schema);

        if ($this->tenantScoped) {
            $fields = [
                'tenant_id' => $schema->integer()
                    ->description('Customer (tenant) id from list_tenants. May be omitted only when the token can access a single customer.'),
                ...$fields,
            ];
        }

        return $fields;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        try {
            $result = $this->execute($request);
        } catch (ToolError $e) {
            return Response::error($e->getMessage());
        }

        return $result instanceof Response || $result instanceof ResponseFactory
            ? $result
            : Response::json($result);
    }

    /**
     * Tool-specific arguments (tenant_id is added automatically).
     *
     * @return array<string, mixed>
     */
    abstract protected function arguments(JsonSchema $schema): array;

    /**
     * @return array<string, mixed>|Response|ResponseFactory
     */
    abstract protected function execute(Request $request): array|Response|ResponseFactory;

    /**
     * Resolves and activates the customer for this call. The tenant global
     * scope then filters every model query, as in the web UI.
     */
    protected function tenant(Request $request): Tenant
    {
        /** @var User $user */
        $user = auth()->user();
        $accessible = AccessibleTenants::query($user);
        $id = $request->get('tenant_id');

        if ($id === null || $id === '') {
            $ids = (clone $accessible)->limit(2)->pluck('tenants.id');
            if ($ids->count() !== 1) {
                throw new ToolError('Specify tenant_id: call list_tenants to see the customers this token can use.');
            }
            $id = $ids->first();
        }

        $tenant = $accessible->whereKey((int) $id)->first();
        if (! $tenant instanceof Tenant) {
            throw new ToolError("Customer {$id} is not accessible with this token. Call list_tenants.");
        }

        TenantContext::setId($tenant->getKey());

        return $tenant;
    }

    /**
     * Calls the REST API inside the resolved customer and returns the
     * flattened payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function api(string $method, string $path, ?Tenant $tenant, array $data = []): array
    {
        return ApiBridge::simplify($this->bridge->call($method, $path, $tenant?->getKey(), $data));
    }

    /**
     * Keeps only the arguments listed in $keys that were actually sent.
     *
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    protected function only(Request $request, array $keys): array
    {
        $all = $request->all();

        return array_intersect_key($all, array_flip($keys));
    }
}
