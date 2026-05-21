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
            $t->string('icon_path')->nullable()->after('notes');
        });

        Schema::table('equipment', function (Blueprint $t): void {
            $t->string('icon_path')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('racks', function (Blueprint $t): void {
            $t->dropColumn('icon_path');
        });

        Schema::table('equipment', function (Blueprint $t): void {
            $t->dropColumn('icon_path');
        });
    }
};
