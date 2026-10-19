<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Services\OAuth\OAuthServer;
use Illuminate\Http\JsonResponse;

/**
 * Discovery documents read by MCP clients after the 401 from /mcp.
 */
class MetadataController extends Controller
{
    /** RFC 9728 Protected Resource Metadata. */
    public function protectedResource(): JsonResponse
    {
        return response()->json([
            'resource' => OAuthServer::resource(),
            'authorization_servers' => [OAuthServer::issuer()],
            'scopes_supported' => [OAuthServer::SCOPE],
            'bearer_methods_supported' => ['header'],
            'resource_name' => config('app.name'),
        ]);
    }

    /** RFC 8414 Authorization Server Metadata. */
    public function authorizationServer(): JsonResponse
    {
        return response()->json([
            'issuer' => OAuthServer::issuer(),
            'authorization_endpoint' => route('oauth.authorize'),
            'token_endpoint' => route('oauth.token'),
            'registration_endpoint' => route('oauth.register'),
            'revocation_endpoint' => route('oauth.revoke'),
            'scopes_supported' => [OAuthServer::SCOPE],
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'revocation_endpoint_auth_methods_supported' => ['none'],
            // RFC 9207: every authorization response carries `iss`.
            'authorization_response_iss_parameter_supported' => true,
        ]);
    }
}
