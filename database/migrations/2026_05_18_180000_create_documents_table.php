<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->date('document_date');
            // Full document structure (which sections are enabled, which
            // items are selected, per-section descriptions, per-snapshot
            // orientation, layout options) in a single JSON blob — keeps
            // the schema flexible and the editor straightforward.
            $t->json('parameters');
            $t->string('pdf_path')->nullable();
            $t->timestamp('generated_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['tenant_id', 'document_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
