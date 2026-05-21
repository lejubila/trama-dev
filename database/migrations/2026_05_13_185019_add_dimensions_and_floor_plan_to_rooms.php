<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->decimal('width_m', 6, 2)->nullable()->after('floor');
            $table->decimal('depth_m', 6, 2)->nullable()->after('width_m');
            $table->string('floor_plan_path')->nullable()->after('depth_m');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropColumn(['width_m', 'depth_m', 'floor_plan_path']);
        });
    }
};
