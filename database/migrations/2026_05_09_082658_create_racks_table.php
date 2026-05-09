<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('racks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->smallInteger('height_units')->default(42);
            $table->integer('width_mm')->nullable()->default(600);
            $table->integer('depth_mm')->nullable()->default(1000);
            $table->decimal('position_x', 8, 2)->nullable();
            $table->decimal('position_y', 8, 2)->nullable();
            $table->string('numbering', 20)->default('bottom_up');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('racks');
    }
};
