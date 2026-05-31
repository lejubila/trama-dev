<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_remote_access_clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vpn_remote_access_id')->constrained('vpn_remote_access')->cascadeOnDelete();
            $table->foreignId('client_interface_id')->constrained('interfaces')->cascadeOnDelete();
            $table->string('username')->nullable();
            $table->timestamps();

            $table->unique(['vpn_remote_access_id', 'client_interface_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_remote_access_clients');
    }
};
