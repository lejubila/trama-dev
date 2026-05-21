<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DeviceIcon;
use App\Models\User;

class DeviceIconPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageData();
    }

    /**
     * Tenant-scoped icon overrides: admin and tecnico.
     */
    public function manage(User $user, ?DeviceIcon $icon = null): bool
    {
        return $user->canManageData();
    }

    /**
     * Global icons: admin only (admin also passes via Gate::before).
     */
    public function manageGlobal(User $user, ?DeviceIcon $icon = null): bool
    {
        return $user->isAdmin();
    }
}
