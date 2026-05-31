<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wifi_network_broadcasters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wifi_network_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interface_id')->constrained('interfaces')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['wifi_network_id', 'interface_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wifi_network_broadcasters');
    }
};
