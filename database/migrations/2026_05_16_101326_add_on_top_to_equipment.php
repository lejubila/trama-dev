<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $t): void {
            // Indicates a rack-mounted device that sits ON TOP of the rack
            // (no rack-unit slot). When true, position_u_start/height are
            // ignored. position_orient (front/rear) is reused here too —
            // it's now also exposed on the equipment form.
            $t->boolean('on_top')->default(false)->after('position_orient');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $t): void {
            $t->dropColumn('on_top');
        });
    }
};
