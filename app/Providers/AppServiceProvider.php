<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        // Admin is a global superuser: it bypasses every Policy. Returning null
        // (not false) lets non-admins fall through to the individual policies.
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);
    }
}
