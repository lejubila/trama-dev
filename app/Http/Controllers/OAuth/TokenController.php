<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Actions\OAuth\ExchangeAuthorizationCode;
use App\Actions\OAuth\RefreshAccessToken;
use App\Http\Controllers\Controller;
use App\Services\OAuth\OAuthException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Token endpoint for public clients: authorization_code (+ PKCE) and
 * refresh_token grants.
 */
class TokenController extends Controller
{
    public function __invoke(Request $request, ExchangeAuthorizationCode $exchange, RefreshAccessToken $refresh): JsonResponse
    {
        // Some clients put the client_id in HTTP Basic even without a secret.
        $clientId = (string) ($request->input('client_id') ?? $request->getUser() ?? '');
        if ($clientId === '') {
            throw new OAuthException('invalid_client', 'client_id is required.', 401);
        }

        $resource = $request->filled('resource') ? (string) $request->input('resource') : null;

        $tokens = match ($request->input('grant_type')) {
            'authorization_code' => $exchange->handle(
                $clientId,
                $this->required($request, 'code'),
                $this->required($request, 'redirect_uri'),
                $this->required($request, 'code_verifier'),
                $resource,
            ),
            'refresh_token' => $refresh->handle($clientId, $this->required($request, 'refresh_token'), $resource),
            default => throw new OAuthException('unsupported_grant_type', 'Use authorization_code or refresh_token.'),
        };

        return response()->json($tokens, 200, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }

    private function required(Request $request, string $key): string
    {
        $value = $request->input($key);
        if (! is_string($value) || $value === '') {
            throw new OAuthException('invalid_request', "{$key} is required.");
        }

        return $value;
    }
}
