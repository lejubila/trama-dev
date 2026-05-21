<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tenant_user', 'role')) {
            Schema::table('tenant_user', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tenant_user', 'role')) {
            Schema::table('tenant_user', function (Blueprint $table) {
                $table->string('role', 20)->nullable();
            });
        }
    }
};
