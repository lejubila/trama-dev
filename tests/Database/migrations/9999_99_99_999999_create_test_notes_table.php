<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only table backing TestNote — a minimal model used to exercise
 * BelongsToTenant + TenantScope without depending on FASE 2 schemas.
 * Loaded via TestCase::setUp() through loadMigrationsFrom(); never registered
 * in app/database/migrations/ and never reaches the dev or prod database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_notes');
    }
};
