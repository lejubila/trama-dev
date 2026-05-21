<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * User management is restricted to admins, who are global superusers and may
 * manage any user regardless of tenant. (Admins also bypass these checks via
 * Gate::before; the explicit isAdmin() keeps the intent clear and acts as
 * defense in depth.) The "cannot delete yourself" rule lives in the Users
 * component, since Gate::before would otherwise let an admin through.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $target): bool
    {
        return $user->isAdmin();
    }
}
