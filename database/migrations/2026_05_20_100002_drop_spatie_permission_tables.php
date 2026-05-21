<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the spatie/laravel-permission tables. Global roles now live on
 * users.role, so the per-tenant (teams) role machinery is gone. Irreversible
 * by design — re-introducing spatie would mean re-publishing its migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }

    public function down(): void
    {
        // No-op: the spatie schema is intentionally not recreated.
    }
};
