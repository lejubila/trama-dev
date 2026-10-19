<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\OAuthGrant;
use App\Models\PersonalAccessToken;
use App\Services\OAuth\TokenIssuer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * RFC 7009 revocation: either token of a connection ends the whole
 * connection. Always 200, so it reveals nothing about unknown tokens.
 */
class RevokeController extends Controller
{
    public function __invoke(Request $request, TokenIssuer $issuer): Response
    {
        $token = (string) $request->input('token', '');

        $grant = OAuthGrant::query()->where('refresh_token_hash', hash('sha256', $token))->first();
        if ($grant === null && $token !== '') {
            $grantId = PersonalAccessToken::findToken($token)?->oauth_grant_id;
            $grant = $grantId !== null ? OAuthGrant::query()->find($grantId) : null;
        }

        if ($grant !== null && ($request->input('client_id') === null || $request->input('client_id') === $grant->client_id)) {
            $issuer->revoke($grant);
        }

        return response()->noContent(200);
    }
}
