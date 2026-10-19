<?php

declare(strict_types=1);

namespace App\Actions\OAuth;

use App\Models\OAuthGrant;
use App\Models\User;
use App\Services\OAuth\OAuthException;
use App\Services\OAuth\OAuthServer;
use App\Services\OAuth\TokenIssuer;
use Illuminate\Support\Facades\Gate;

/**
 * refresh_token grant with rotation: every refresh returns a new refresh
 * token, and presenting an old one revokes the whole connection.
 */
class RefreshAccessToken
{
    public function __construct(private readonly TokenIssuer $issuer) {}

    /**
     * @return array<string, int|string>
     */
    public function handle(string $clientId, string $refreshToken, ?string $resource): array
    {
        if (! OAuthServer::isOwnResource($resource)) {
            throw new OAuthException('invalid_target', 'Unknown resource.');
        }

        $hash = hash('sha256', $refreshToken);
        $grant = OAuthGrant::query()->where('refresh_token_hash', $hash)->first();

        if ($grant === null) {
            $leaked = OAuthGrant::query()->where('previous_refresh_token_hash', $hash)->first();
            if ($leaked !== null) {
                $this->issuer->revoke($leaked);
            }
            throw new OAuthException('invalid_grant', 'Unknown or already used refresh token.');
        }

        if (! hash_equals($grant->client_id, $clientId)) {
            throw new OAuthException('invalid_grant', 'The refresh token belongs to another client.');
        }
        if (! $grant->isActive()) {
            throw new OAuthException('invalid_grant', 'The connection has expired or was revoked.');
        }

        /** @var User $user */
        $user = $grant->user;
        if (! Gate::forUser($user)->allows('manage-api-tokens')) {
            $this->issuer->revoke($grant);
            throw new OAuthException('invalid_grant', 'The user can no longer connect AI clients.');
        }

        // Rotate atomically: of two parallel refreshes with the same token
        // only one gets through; the other then looks like a replay.
        $claimed = OAuthGrant::query()->whereKey($grant->id)->where('refresh_token_hash', $hash)
            ->update(['refresh_token_hash' => null, 'previous_refresh_token_hash' => $hash]);
        if ($claimed === 0) {
            throw new OAuthException('invalid_grant', 'Unknown or already used refresh token.');
        }

        return $this->issuer->issue($grant->refresh());
    }
}
