<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vpn_remote_access', function (Blueprint $table): void {
            $table->string('routing_mode', 16)->default('routed')->after('protocol');
            $table->string('client_network_cidr', 43)->nullable()->after('routing_mode');
        });
    }

    public function down(): void
    {
        Schema::table('vpn_remote_access', function (Blueprint $table): void {
            $table->dropColumn(['routing_mode', 'client_network_cidr']);
        });
    }
};
