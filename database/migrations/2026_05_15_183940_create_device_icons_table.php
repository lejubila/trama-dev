<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_icons', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('kind', 40);
            $t->string('image_path');
            $t->timestamps();

            $t->index('tenant_id');
            $t->index('kind');
        });

        // Postgres partial unique indices: one global icon per kind, one
        // tenant-specific icon per (tenant, kind). Plain UNIQUE() can't be
        // used because (NULL, kind) duplicates are not deduplicated.
        DB::statement('CREATE UNIQUE INDEX device_icons_global_kind_unique ON device_icons (kind) WHERE tenant_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX device_icons_tenant_kind_unique ON device_icons (tenant_id, kind) WHERE tenant_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS device_icons_tenant_kind_unique');
        DB::statement('DROP INDEX IF EXISTS device_icons_global_kind_unique');
        Schema::dropIfExists('device_icons');
    }
};
