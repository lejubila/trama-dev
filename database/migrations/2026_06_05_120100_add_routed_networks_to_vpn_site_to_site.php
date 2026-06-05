<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vpn_site_to_site', function (Blueprint $table): void {
            $table->json('routed_networks_a')->nullable()->after('routed_vlans_a');
            $table->json('routed_networks_b')->nullable()->after('routed_vlans_b');
        });
    }

    public function down(): void
    {
        Schema::table('vpn_site_to_site', function (Blueprint $table): void {
            $table->dropColumn(['routed_networks_a', 'routed_networks_b']);
        });
    }
};
