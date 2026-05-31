<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_site_to_site', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('protocol');
            $table->foreignId('endpoint_a_interface_id')->constrained('interfaces');
            $table->foreignId('endpoint_b_interface_id')->constrained('interfaces');
            $table->json('routed_vlans_a')->nullable();
            $table->json('routed_vlans_b')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_site_to_site');
    }
};
