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
            self::Admin => 'Amministratore',
            self::Tecnico => 'Tecnico',
            self::Cliente => 'Cliente',
        };
    }

    /**
     * Admin and tecnico can create/update/delete tenant data.
     */
    public function canManageData(): bool
    {
        return $this === self::Admin || $this === self::Tecnico;
    }

    /**
     * Rank for picking the highest role when backfilling from old pivot rows.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Admin => 3,
            self::Tecnico => 2,
            self::Cliente => 1,
        };
    }
}
