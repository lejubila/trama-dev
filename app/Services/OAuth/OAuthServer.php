<?php

declare(strict_types=1);

namespace App\Services\OAuth;

/**
 * Identifiers shared by the discovery documents, the authorization
 * responses and the token checks. They must be byte-for-byte identical
 * everywhere: ChatGPT compares `iss` with the metadata issuer verbatim.
 */
final class OAuthServer
{
    public const SCOPE = 'mcp';

    public static function issuer(): string
    {
        return rtrim(url('/'), '/');
    }

    /** The protected resource: the MCP endpoint. */
    public static function resource(): string
    {
        return self::issuer().'/mcp';
    }

    /**
     * RFC 8707: a client may name the resource it wants a token for; we
     * only serve the MCP endpoint (a trailing slash is tolerated).
     */
    public static function isOwnResource(?string $resource): bool
    {
        return $resource === null || $resource === '' || rtrim($resource, '/') === self::resource();
    }

    /**
     * Whether a dynamically registered client may use this redirect URI,
     * matched component by component against config('oauth.redirect_uris')
     * so tricks like "http://localhost:@evil.example" cannot slip through.
     */
    public static function redirectAllowed(string $uri): bool
    {
        $candidate = parse_url($uri);
        if ($candidate === false || ! isset($candidate['scheme'], $candidate['host'])
            || isset($candidate['user']) || isset($candidate['pass']) || isset($candidate['fragment'])) {
            return false;
        }

        foreach ((array) config('oauth.redirect_uris', []) as $pattern) {
            if (self::matches((string) $pattern, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, int|string>  $candidate
     */
    private static function matches(string $pattern, array $candidate): bool
    {
        // parse_url() rejects a "*" port, so swap it for a placeholder first.
        $anyPort = str_contains($pattern, ':*');
        $rule = parse_url(str_replace(':*', ':1', $pattern));
        if ($rule === false || ! isset($rule['scheme'], $rule['host'])) {
            return false;
        }

        if (strtolower((string) $candidate['scheme']) !== $rule['scheme']
            || strtolower((string) $candidate['host']) !== $rule['host']) {
            return false;
        }

        if (! $anyPort && ($candidate['port'] ?? null) !== ($rule['port'] ?? null)) {
            return false;
        }

        $path = (string) ($candidate['path'] ?? '');
        $rulePath = $rule['path'] ?? '';

        if (str_ends_with($rulePath, '*')) {
            return str_starts_with($path, substr($rulePath, 0, -1));
        }

        // An exact URI: no extra query string either.
        return $path === $rulePath && ! isset($candidate['query']);
    }
}
