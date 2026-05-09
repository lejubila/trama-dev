<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interfaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('type', 20);
            $table->integer('index')->default(0);
            $table->integer('speed_mbps')->nullable();
            $table->string('media', 20);
            $table->string('connector', 20)->nullable();
            $table->string('vlan_mode', 20)->nullable();
            $table->smallInteger('vlan_default')->nullable();
            $table->jsonb('vlans_allowed')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('mac_address', 17)->nullable();
            $table->string('status', 15)->default('unknown');
            $table->string('poe', 10)->default('none');
            $table->string('description', 255)->nullable();
            $table->jsonb('custom_fields')->default('{}');
            $table->timestamps();

            $table->unique(['equipment_id', 'name']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interfaces');
    }
};
