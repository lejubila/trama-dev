<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\NetworkInterface;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Observers\NetworkInterfacePairObserver;
use App\Support\Demo;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Behind a TLS-terminating reverse proxy that doesn't send
        // X-Forwarded-Proto, force generated URLs/assets to https.
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        // Demo mode: nobody may manage users, not even the (admin) demo
        // account. Registered before the admin bypass so false wins.
        Gate::before(function (User $user, string $ability, array $arguments): ?bool {
            $target = $arguments[0] ?? null;
            $onUsers = $target instanceof User || $target === User::class;

            return Demo::enabled() && $onUsers && in_array($ability, Demo::LOCKED_USER_ABILITIES, true) ? false : null;
        });

        Blade::if('demo', fn (): bool => Demo::enabled());

        // Admin is a global superuser: it bypasses every Policy. Returning null
        // (not false) lets non-admins fall through to the individual policies.
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);

        // API tokens are long-lived credentials: only admins may mint them.
        Gate::define('manage-api-tokens', fn (User $user): bool => $user->isAdmin());

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        NetworkInterface::observe(NetworkInterfacePairObserver::class);
    }
}
