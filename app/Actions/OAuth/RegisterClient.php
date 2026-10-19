<?php

declare(strict_types=1);

namespace App\Actions\OAuth;

use App\Models\OAuthClient;

/**
 * RFC 7591 Dynamic Client Registration of a public client.
 */
class RegisterClient
{
    /**
     * @param  array{client_name?: string|null, redirect_uris: list<string>, client_uri?: string|null, logo_uri?: string|null}  $data
     */
    public function handle(array $data): OAuthClient
    {
        return OAuthClient::create([
            'name' => $data['client_name'] ?? parse_url($data['redirect_uris'][0], PHP_URL_HOST) ?? 'MCP client',
            'redirect_uris' => array_values(array_unique($data['redirect_uris'])),
            'client_uri' => $data['client_uri'] ?? null,
            'logo_uri' => $data['logo_uri'] ?? null,
        ]);
    }
}
