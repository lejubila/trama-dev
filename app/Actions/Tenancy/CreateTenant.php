<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a new tenant ("cliente") and lands the creator inside it.
 *
 * With global roles there is nothing to assign on creation: the creator is an
 * admin or tecnico (only they may create tenants) and therefore already sees
 * every tenant. We just insert the row and flip current_tenant_id.
 */
class CreateTenant
{
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
