<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTenantHeader;
use App\Http\Middleware\ForceHttpsRequest;
use App\Http\Middleware\SetCurrentTenant;
use App\Http\Middleware\SetLocale;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            RateLimiter::for('api', fn (Request $r) => Limit::perMinute(60)->by(
                $r->user()?->getAuthIdentifier() ?? $r->ip(),
            ));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(prepend: [
            ForceHttpsRequest::class,
        ], append: [
            SetLocale::class,
            SetCurrentTenant::class,
        ])
            ->trustProxies(at: '*');
        $middleware->alias([
            'tenant.required' => EnsureTenantHeader::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
