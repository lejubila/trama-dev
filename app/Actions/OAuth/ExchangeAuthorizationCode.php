<?php

declare(strict_types=1);

namespace App\Actions\OAuth;

use App\Models\OAuthAuthCode;
use App\Models\OAuthGrant;
use App\Models\User;
use App\Services\OAuth\OAuthException;
use App\Services\OAuth\OAuthServer;
use App\Services\OAuth\TokenIssuer;
use App\Support\Api\TokenAbilities;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * authorization_code grant: verifies the code (single use, not expired,
 * same client and redirect URI, PKCE S256) and opens the connection.
 */
class ExchangeAuthorizationCode
{
    public function __construct(private readonly TokenIssuer $issuer) {}

    /**
     * @return array<string, int|string>
     */
    public function handle(string $clientId, string $code, string $redirectUri, string $verifier, ?string $resource): array
    {
        $authCode = OAuthAuthCode::query()->where('code_hash', hash('sha256', $code))->first();

        if ($authCode === null || ! hash_equals($authCode->client_id, $clientId)) {
            throw new OAuthException('invalid_grant', 'Unknown authorization code.');
        }

        // Claimed atomically and outside any transaction, so a failed check
        // below still burns the code and two parallel exchanges cannot both win.
        $claimed = OAuthAuthCode::query()->whereKey($authCode->id)->whereNull('used_at')->update(['used_at' => now()]);
        if ($claimed === 0) {
            // RFC 6749 §4.1.2: a replayed code revokes what it produced.
            $grantId = $authCode->fresh()?->grant_id;
            $grant = $grantId !== null ? OAuthGrant::query()->find($grantId) : null;
            if ($grant !== null) {
                $this->issuer->revoke($grant);
            }
            throw new OAuthException('invalid_grant', 'Authorization code already used.');
        }

        if ($authCode->expires_at->isPast()) {
            throw new OAuthException('invalid_grant', 'Authorization code expired.');
        }
        if ($authCode->redirect_uri !== $redirectUri) {
            throw new OAuthException('invalid_grant', 'redirect_uri does not match the authorization request.');
        }
        if (! hash_equals($authCode->code_challenge, self::challenge($verifier))) {
            throw new OAuthException('invalid_grant', 'PKCE verification failed.');
        }
        if (! OAuthServer::isOwnResource($resource)) {
            throw new OAuthException('invalid_target', 'Unknown resource.');
        }

        /** @var User $user */
        $user = $authCode->user;
        if (! Gate::forUser($user)->allows('manage-api-tokens')) {
            throw new OAuthException('invalid_grant', 'The user can no longer connect AI clients.');
        }

        return DB::transaction(function () use ($authCode, $user): array {
            // Reconnecting the same app replaces the previous connection.
            OAuthGrant::query()->where('client_id', $authCode->client_id)->where('user_id', $user->getKey())
                ->whereNull('revoked_at')->get()->each(fn (OAuthGrant $old) => $this->issuer->revoke($old));

            $grant = OAuthGrant::create([
                'client_id' => $authCode->client_id,
                'user_id' => $user->getKey(),
                'abilities' => TokenAbilities::normalize($authCode->abilities),
                'tenant_ids' => $authCode->tenant_ids,
                'resource' => $authCode->resource ?? OAuthServer::resource(),
            ]);
            $authCode->forceFill(['grant_id' => $grant->id])->save();

            return $this->issuer->issue($grant);
        });
    }

    /** RFC 7636 S256: BASE64URL(SHA256(verifier)) without padding. */
    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
