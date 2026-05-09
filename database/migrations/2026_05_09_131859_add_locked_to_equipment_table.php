<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            // Pinned equipment is excluded from the rack drag&drop reposition
            // flow. Useful for "do not touch" rows like UPS or core firewalls.
            $table->boolean('locked')->default(false)->after('mounted');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('locked');
        });
    }
};
