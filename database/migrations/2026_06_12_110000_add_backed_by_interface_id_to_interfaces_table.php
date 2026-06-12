<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interfaces', function (Blueprint $table): void {
            $table->foreignId('backed_by_interface_id')->nullable()->after('paired_interface_id')
                ->constrained('interfaces')->nullOnDelete();
            $table->index(['tenant_id', 'backed_by_interface_id']);
        });
    }

    public function down(): void
    {
        Schema::table('interfaces', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'backed_by_interface_id']);
            $table->dropConstrainedForeignId('backed_by_interface_id');
        });
    }
};
