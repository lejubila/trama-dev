<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_interface_id')->constrained('interfaces')->cascadeOnDelete();
            $table->foreignId('to_interface_id')->constrained('interfaces')->cascadeOnDelete();
            $table->string('cable_type', 30);
            $table->decimal('cable_length_m', 6, 2)->nullable();
            $table->string('cable_label', 80)->nullable();
            $table->string('color', 20)->nullable();
            $table->string('status', 15)->default('active');
            $table->text('notes')->nullable();
            $table->date('established_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('from_interface_id');
            $table->index('to_interface_id');
        });

        // Partial unique indexes: an interface may participate in at most ONE
        // active connection (from or to), but planned/decommissioned rows for
        // the same interface are fine.
        DB::statement("
            CREATE UNIQUE INDEX connections_from_active_unique
              ON connections (from_interface_id) WHERE status = 'active'
        ");
        DB::statement("
            CREATE UNIQUE INDEX connections_to_active_unique
              ON connections (to_interface_id) WHERE status = 'active'
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS connections_from_active_unique');
        DB::statement('DROP INDEX IF EXISTS connections_to_active_unique');
        Schema::dropIfExists('connections');
    }
};
