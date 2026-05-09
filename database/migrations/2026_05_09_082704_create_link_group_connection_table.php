<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_group_connection', function (Blueprint $table) {
            $table->foreignId('link_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained()->cascadeOnDelete();

            $table->primary(['link_group_id', 'connection_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_group_connection');
    }
};
