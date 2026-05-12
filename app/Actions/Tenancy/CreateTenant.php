<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantRoleBootstrapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates a new tenant ("workspace") and seats the creator as its admin.
 *
 * In a single transaction:
 *  1. inserts the Tenant row;
 *  2. bootstraps the three spatie roles inside the new tenant team scope;
 *  3. attaches the creator with role=admin on the pivot;
 *  4. assigns the spatie 'admin' role to the creator in the new tenant;
 *  5. flips the creator's current_tenant_id to the new tenant so the next
 *     page load lands them inside the freshly-created workspace.
 */
class CreateTenant
{
    public function __construct(
        private readonly TenantRoleBootstrapper $bootstrapper,
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * @param  array{name: string, slug?: string|null, domain?: string|null}  $attributes
     */
    public function execute(User $creator, array $attributes): Tenant
    {
        return DB::transaction(function () use ($creator, $attributes): Tenant {
            $slug = $attributes['slug'] ?? null;
            if ($slug === null || trim($slug) === '') {
                $slug = $this->uniqueSlugFor($attributes['name']);
            }

            $tenant = Tenant::create([
                'name' => $attributes['name'],
                'slug' => $slug,
                'domain' => $attributes['domain'] ?? null,
                'settings' => [],
            ]);

            // Roles inside the new tenant first, so the syncRoles below resolves.
            $this->bootstrapper->bootstrapFor($tenant);

            $creator->tenants()->attach($tenant->getKey(), ['role' => 'admin']);

            $previousTeam = $this->registrar->getPermissionsTeamId();
            $this->registrar->setPermissionsTeamId($tenant->getKey());
            try {
                $creator->syncRoles(['admin']);
            } finally {
                $this->registrar->setPermissionsTeamId($previousTeam);
            }

            $creator->forceFill(['current_tenant_id' => $tenant->getKey()])->save();

            return $tenant;
        });
    }

    private function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name) !== '' ? Str::slug($name) : 'cliente';
        $candidate = $base;
        $i = 2;
        while (Tenant::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$i++;
        }

        return $candidate;
    }
}
