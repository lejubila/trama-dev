<?php

declare(strict_types=1);

/*
 * OAuth 2.1 authorization server for the MCP endpoint, used by AI clients
 * that cannot send a custom Authorization header (claude.ai, ChatGPT). The
 * tokens it issues are ordinary Sanctum tokens linked to an oauth_grant.
 */
return [
    'access_token_ttl' => (int) env('OAUTH_ACCESS_TOKEN_TTL', 3600),
    'refresh_token_ttl_days' => (int) env('OAUTH_REFRESH_TOKEN_TTL_DAYS', 30),
    'auth_code_ttl' => 600,

    /*
     * Redirect URIs a dynamically registered client may use. "*" as port
     * means any port; a path ending in "*" is a prefix. Add more with
     * OAUTH_EXTRA_REDIRECT_URIS (comma-separated).
     */
    'redirect_uris' => array_values(array_filter([
        'https://claude.ai/api/mcp/auth_callback',
        'https://claude.com/api/mcp/auth_callback',
        'https://chatgpt.com/connector_platform_oauth_redirect',
        'https://chatgpt.com/connector/oauth/*',
        // Local clients: Claude Code, MCP Inspector, Cursor…
        'http://localhost:*/*',
        'http://127.0.0.1:*/*',
        ...array_map('trim', explode(',', (string) env('OAUTH_EXTRA_REDIRECT_URIS', ''))),
    ])),
];
