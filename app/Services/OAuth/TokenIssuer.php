<?php

declare(strict_types=1);

namespace App\Services\OAuth;

use App\Models\OAuthGrant;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\Api\TokenAbilities;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns an OAuth grant into credentials: a short-lived Sanctum token (so
 * abilities, tenant allow-list, MCP and API keep working unchanged) plus a
 * rotating refresh token.
 */
class TokenIssuer
{
    /**
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string, scope: string}
     */
    public function issue(OAuthGrant $grant): array
    {
        $ttl = (int) config('oauth.access_token_ttl');
        $refresh = Str::random(64);

        $access = DB::transaction(function () use ($grant, $ttl, $refresh): string {
            // One live access token per grant: the previous one dies here.
            $grant->tokens()->delete();

            /** @var User $user */
            $user = $grant->user;
            $new = $user->createToken(
                $grant->client?->name ?? 'OAuth',
                [...TokenAbilities::normalize($grant->abilities), TokenAbilities::MCP],
                now()->addSeconds($ttl),
            );
            /** @var PersonalAccessToken $token */
            $token = $new->accessToken;
            $token->forceFill(['tenant_ids' => $grant->tenant_ids, 'oauth_grant_id' => $grant->id])->save();

            $grant->forceFill([
                'previous_refresh_token_hash' => $grant->refresh_token_hash ?? $grant->previous_refresh_token_hash,
                'refresh_token_hash' => hash('sha256', $refresh),
                'refresh_expires_at' => now()->addDays((int) config('oauth.refresh_token_ttl_days')),
            ])->save();

            return $new->plainTextToken;
        });

        return [
            'access_token' => $access,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'refresh_token' => $refresh,
            'scope' => OAuthServer::SCOPE,
        ];
    }

    /**
     * Applies new permissions to a grant and to its live access token.
     *
     * @param  list<string>  $abilities
     * @param  list<int>|null  $tenantIds
     */
    public function updatePermissions(OAuthGrant $grant, array $abilities, ?array $tenantIds): void
    {
        DB::transaction(function () use ($grant, $abilities, $tenantIds): void {
            $grant->update(['abilities' => $abilities, 'tenant_ids' => $tenantIds]);
            $grant->tokens()->update([
                'abilities' => json_encode([...$abilities, TokenAbilities::MCP]),
                'tenant_ids' => $tenantIds === null ? null : json_encode($tenantIds),
            ]);
        });
    }

    public function revoke(OAuthGrant $grant): void
    {
        DB::transaction(function () use ($grant): void {
            $grant->tokens()->delete();
            $grant->forceFill(['revoked_at' => now(), 'refresh_token_hash' => null, 'previous_refresh_token_hash' => null])->save();
        });
    }
}
