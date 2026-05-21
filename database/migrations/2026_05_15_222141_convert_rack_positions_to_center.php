<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Coords previously anchored at the top-left of the rack icon.
        // Shift by half the icon size (in meters) so they now anchor at the
        // center, keeping every existing rack visually in the same spot.
        // SCALE=50 px/m, default icon=40px → 0.4m shift.
        DB::statement(<<<'SQL'
            UPDATE racks
               SET position_x = position_x + (COALESCE(icon_size_px, 40)::numeric / 2 / 50),
                   position_y = position_y + (COALESCE(icon_size_px, 40)::numeric / 2 / 50)
             WHERE position_x IS NOT NULL AND position_y IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            UPDATE racks
               SET position_x = position_x - (COALESCE(icon_size_px, 40)::numeric / 2 / 50),
                   position_y = position_y - (COALESCE(icon_size_px, 40)::numeric / 2 / 50)
             WHERE position_x IS NOT NULL AND position_y IS NOT NULL
        SQL);
    }
};
