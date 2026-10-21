<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Single global role per user (no longer per-tenant).
 *
 *  - admin   : superuser — manages all users, all tenants and their data, and
 *              the global icon set. Bypasses every Policy via Gate::before.
 *  - tecnico : manages data of every tenant and tenant-scoped icons, but cannot
 *              manage users nor the global icons.
 *  - cliente : read-only, and only on the tenants explicitly assigned via the
 *              tenant_user pivot.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Tecnico = 'tecnico';
    case Cliente = 'cliente';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('enums.user_role.admin'),
            self::Tecnico => __('enums.user_role.tecnico'),
            self::Cliente => __('enums.user_role.cliente'),
        };
    }

    /**
     * Admin and tecnico can create/update/delete tenant data.
     */
    public function canManageData(): bool
    {
        return $this === self::Admin || $this === self::Tecnico;
    }
}
