<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Api\TokenAbilities;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The MCP endpoint is opt-in per token: it needs the special mcp:access
 * ability on top of the resource abilities each tool requires.
 */
class EnsureMcpAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->tokenCan(TokenAbilities::MCP)) {
            return response()->json(['error' => 'Token lacks ability: '.TokenAbilities::MCP], 403);
        }

        return $next($request);
    }
}
