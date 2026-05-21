<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Resolve the UI locale for the request, in priority order:
     *  1. authenticated user's saved preference,
     *  2. session override (e.g. a guest who switched language),
     *  3. browser Accept-Language negotiation,
     *  4. configured fallback (it).
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $supported */
        $supported = config('app.supported_locales', ['it', 'en']);

        $locale = $request->user()?->preferredLocale();

        if ($locale === null) {
            $session = $request->session()->get('locale');
            if (is_string($session) && in_array($session, $supported, true)) {
                $locale = $session;
            }
        }

        $locale ??= $request->getPreferredLanguage($supported);

        App::setLocale($locale ?? config('app.fallback_locale'));

        return $next($request);
    }
}
