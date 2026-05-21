<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $t): void {
            $t->foreignId('room_id')
                ->nullable()
                ->after('rack_id')
                ->constrained('rooms')
                ->nullOnDelete();
        });

        // Backfill room_id from the rack's room for existing racked equipment.
        DB::statement('
            UPDATE equipment SET room_id = racks.room_id
            FROM racks
            WHERE equipment.rack_id = racks.id AND equipment.room_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('room_id');
        });
    }
};
