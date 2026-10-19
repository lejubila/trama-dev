<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_clients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->json('redirect_uris');
            $table->string('client_uri', 2048)->nullable();
            $table->string('logo_uri', 2048)->nullable();
            $table->timestamps();
        });

        // One row per approved connection (client × user); its tokens are
        // regular Sanctum tokens pointing back here.
        Schema::create('oauth_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('client_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('abilities');
            $table->json('tenant_ids')->nullable();
            $table->string('resource', 2048)->nullable();
            $table->string('refresh_token_hash', 64)->nullable()->unique();
            // The refresh token just rotated away: presenting it again means
            // it leaked, so the whole grant is revoked.
            $table->string('previous_refresh_token_hash', 64)->nullable()->index();
            $table->timestamp('refresh_expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('oauth_auth_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code_hash', 64)->unique();
            $table->foreignUuid('client_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('abilities');
            $table->json('tenant_ids')->nullable();
            $table->text('redirect_uri');
            $table->string('code_challenge', 128);
            $table->string('resource', 2048)->nullable();
            // Set on exchange: a replayed code revokes what it produced.
            $table->foreignId('grant_id')->nullable()->constrained('oauth_grants')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->foreignId('oauth_grant_id')->nullable()->after('tenant_ids')
                ->constrained('oauth_grants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('oauth_grant_id');
        });
        Schema::dropIfExists('oauth_auth_codes');
        Schema::dropIfExists('oauth_grants');
        Schema::dropIfExists('oauth_clients');
    }
};
