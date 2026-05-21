<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('racks', function (Blueprint $t): void {
            $t->unsignedSmallInteger('icon_size_px')->nullable()->after('icon_path');
        });

        Schema::table('equipment', function (Blueprint $t): void {
            $t->unsignedSmallInteger('icon_size_px')->nullable()->after('icon_path');
        });
    }

    public function down(): void
    {
        Schema::table('racks', function (Blueprint $t): void {
            $t->dropColumn('icon_size_px');
        });

        Schema::table('equipment', function (Blueprint $t): void {
            $t->dropColumn('icon_size_px');
        });
    }
};
