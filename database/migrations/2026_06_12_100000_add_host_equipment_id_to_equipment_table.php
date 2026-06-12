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
            $table->foreignId('host_equipment_id')->nullable()->after('rack_id')
                ->constrained('equipment')->nullOnDelete();
            $table->index(['tenant_id', 'host_equipment_id']);
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'host_equipment_id']);
            $table->dropConstrainedForeignId('host_equipment_id');
        });
    }
};
