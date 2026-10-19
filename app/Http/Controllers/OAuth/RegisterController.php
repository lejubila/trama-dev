<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Actions\OAuth\RegisterClient;
use App\Http\Controllers\Controller;
use App\Services\OAuth\OAuthServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * RFC 7591 Dynamic Client Registration. Clients are always public (PKCE,
 * no secret) and limited to the redirect URIs allowed in config/oauth.php.
 */
class RegisterController extends Controller
{
    public function __invoke(Request $request, RegisterClient $register): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_name' => 'nullable|string|max:255',
            'redirect_uris' => 'required|array|min:1|max:10',
            'redirect_uris.*' => 'required|string|max:2048',
            'client_uri' => 'nullable|url:http,https|max:2048',
            'logo_uri' => 'nullable|url:http,https|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->error('invalid_client_metadata', $validator->errors()->first());
        }

        /** @var array{client_name?: string|null, redirect_uris: list<string>, client_uri?: string|null, logo_uri?: string|null} $data */
        $data = $validator->validated();

        foreach ($data['redirect_uris'] as $uri) {
            if (! OAuthServer::redirectAllowed($uri)) {
                return $this->error('invalid_redirect_uri', "Redirect URI not allowed: {$uri}");
            }
        }

        $client = $register->handle($data);

        return response()->json(array_filter([
            'client_id' => $client->id,
            'client_id_issued_at' => $client->created_at?->getTimestamp(),
            'client_name' => $client->name,
            'redirect_uris' => $client->redirect_uris,
            'client_uri' => $client->client_uri,
            'logo_uri' => $client->logo_uri,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'scope' => OAuthServer::SCOPE,
        ], fn ($v) => $v !== null), 201, ['Cache-Control' => 'no-store']);
    }

    private function error(string $error, string $description): JsonResponse
    {
        return response()->json(['error' => $error, 'error_description' => $description], 400);
    }
}
