<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // 'equipment' for now; future types can extend the taxonomy.
            $table->string('type', 30);
            $table->string('file_path', 255)->nullable();
            $table->string('status', 20)->default('pending'); // pending|completed|failed
            $table->jsonb('summary')->default('{}'); // {created, updated, skipped, errors:[{row,messages}]}
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
