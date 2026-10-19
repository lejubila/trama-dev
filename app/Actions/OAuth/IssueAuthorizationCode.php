<?php

declare(strict_types=1);

namespace App\Actions\OAuth;

use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Records what the admin approved on the consent page and returns the
 * one-time code to send back to the client.
 */
class IssueAuthorizationCode
{
    /**
     * @param  list<string>  $abilities
     * @param  list<int>|null  $tenantIds
     */
    public function handle(User $user, OAuthClient $client, array $abilities, ?array $tenantIds, string $redirectUri, string $codeChallenge, ?string $resource): string
    {
        $code = Str::random(64);

        OAuthAuthCode::create([
            'code_hash' => hash('sha256', $code),
            'client_id' => $client->id,
            'user_id' => $user->getKey(),
            'abilities' => $abilities,
            'tenant_ids' => $tenantIds,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $codeChallenge,
            'resource' => $resource,
            'expires_at' => now()->addSeconds((int) config('oauth.auth_code_ttl')),
        ]);

        return $code;
    }
}
