<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\NetworkInterface;
use App\Models\User;
use App\Observers\NetworkInterfacePairObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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

        // Admin is a global superuser: it bypasses every Policy. Returning null
        // (not false) lets non-admins fall through to the individual policies.
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);

        NetworkInterface::observe(NetworkInterfacePairObserver::class);
    }
}
