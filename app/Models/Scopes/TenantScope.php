<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Log;

final class TenantScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = TenantContext::id();

        if ($tenantId === null) {
            // No tenant context: typical in artisan commands or seeders.
            // We do NOT filter, but log a warning so unintended unscoped queries
            // in HTTP/Livewire flows surface in logs.
            if (app()->runningInConsole() === false) {
                Log::warning('Unscoped tenant query in non-console context', [
                    'model' => $model::class,
                ]);
            }

            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
