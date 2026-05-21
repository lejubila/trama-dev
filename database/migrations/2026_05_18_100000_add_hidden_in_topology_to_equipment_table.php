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
            $t->boolean('hidden_in_topology')->default(false)->after('on_top');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $t): void {
            $t->dropColumn('hidden_in_topology');
        });
    }
};
