<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rack_photos', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('rack_id')->constrained()->cascadeOnDelete();
            $t->string('photo_path');
            $t->string('caption', 500)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['tenant_id', 'rack_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rack_photos');
    }
};
