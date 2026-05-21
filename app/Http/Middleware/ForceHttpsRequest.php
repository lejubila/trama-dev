<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Behind a TLS-terminating reverse proxy that does NOT forward the
 * X-Forwarded-Proto header (e.g. an Apache mod_proxy we can't configure),
 * Laravel sees the request as plain http. That breaks signed-URL validation
 * (Livewire file uploads return 401) even when APP_FORCE_HTTPS makes generated
 * URLs https.
 *
 * When APP_FORCE_HTTPS is enabled we mark the incoming request as secure so
 * $request->isSecure() / $request->url() use https, matching the signature.
 */
class ForceHttpsRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.force_https')) {
            $request->server->set('HTTPS', 'on');
            $request->headers->set('X-Forwarded-Proto', 'https');
        }

        return $next($request);
    }
}
