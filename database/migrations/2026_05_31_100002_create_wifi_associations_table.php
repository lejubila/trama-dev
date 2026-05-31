<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wifi_associations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wifi_network_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_interface_id')->constrained('interfaces')->cascadeOnDelete();
            $table->foreignId('preferred_broadcaster_interface_id')
                ->nullable()
                ->constrained('interfaces')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['wifi_network_id', 'client_interface_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wifi_associations');
    }
};
