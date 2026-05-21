<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $t): void {
            $t->unsignedSmallInteger('rack_icon_size_px')->nullable()->after('floor_plan_path');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $t): void {
            $t->dropColumn('rack_icon_size_px');
        });
    }
};
