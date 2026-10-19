<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Api\TokenAbilities;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the per-token "{resource}:{action}" abilities, derived from the
 * route name. Unmapped routes are denied so a new endpoint can't slip
 * through without an explicit ability in TokenAbilities.
 */
class EnsureTokenAbility
{
    public function handle(Request $request, Closure $next): Response
    {
        $ability = TokenAbilities::forRoute($request->route()?->getName());

        if ($ability === null) {
            return response()->json(['error' => 'Endpoint not available to API tokens'], 403);
        }

        if (! $request->user()?->tokenCan($ability)) {
            return response()->json(['error' => "Token lacks ability: {$ability}"], 403);
        }

        return $next($request);
    }
}
