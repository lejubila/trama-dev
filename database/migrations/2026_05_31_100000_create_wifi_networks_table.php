<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wifi_networks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('ssid');
            $table->string('security_type')->nullable();
            $table->unsignedSmallInteger('vlan_id')->nullable();
            $table->boolean('hidden_ssid')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'ssid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wifi_networks');
    }
};
