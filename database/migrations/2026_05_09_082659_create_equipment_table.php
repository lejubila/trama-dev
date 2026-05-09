<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rack_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('type', 30);
            $table->string('vendor', 80)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('serial', 120)->nullable();
            $table->string('firmware', 80)->nullable();
            $table->string('asset_tag', 80)->nullable();
            $table->boolean('mounted')->default(false);
            $table->smallInteger('position_u_start')->nullable();
            $table->smallInteger('position_u_height')->nullable();
            $table->string('position_orient', 10)->nullable();
            $table->string('status', 20)->default('active');
            // PostgreSQL inet type for IPv4/IPv6 management addresses
            $table->ipAddress('management_ip')->nullable();
            $table->text('description')->nullable();
            $table->jsonb('custom_fields')->default('{}');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('rack_id');
            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
