<?php

declare(strict_types=1);

namespace App\Validation;

use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\Rules\Exists;

/**
 * `exists:` hits the table directly, bypassing the BelongsToTenant scope:
 * this variant only accepts live rows of the active tenant.
 */
final class TenantExists
{
    public static function in(string $table, bool $softDeletes = true): Exists
    {
        $rule = (new Exists($table, 'id'))->where('tenant_id', TenantContext::id());

        return $softDeletes ? $rule->whereNull('deleted_at') : $rule;
    }
}
