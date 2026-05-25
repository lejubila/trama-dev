<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table): void {
            $table->decimal('position_x', 6, 2)->nullable()->after('room_id');
            $table->decimal('position_y', 6, 2)->nullable()->after('position_x');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table): void {
            $table->dropColumn(['position_x', 'position_y']);
        });
    }
};
