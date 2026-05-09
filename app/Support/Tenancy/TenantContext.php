<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

final class TenantContext
{
    private static ?int $tenantId = null;

    public static function setId(?int $id): void
    {
        self::$tenantId = $id;
    }

    public static function id(): ?int
    {
        return self::$tenantId;
    }

    public static function clear(): void
    {
        self::$tenantId = null;
    }

    public static function isSet(): bool
    {
        return self::$tenantId !== null;
    }
}
