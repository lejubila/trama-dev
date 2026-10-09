<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Demo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides routes that make no sense on the public demo (e.g. password reset
 * of the shared account) by answering 404 as if they didn't exist.
 */
class DisabledInDemo
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Demo::enabled(), 404);

        return $next($request);
    }
}
