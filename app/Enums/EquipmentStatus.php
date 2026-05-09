<?php

declare(strict_types=1);

namespace App\Enums;

enum EquipmentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';
    case Decommissioned = 'decommissioned';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Attivo',
            self::Inactive => 'Inattivo',
            self::Maintenance => 'In manutenzione',
            self::Decommissioned => 'Dismesso',
        };
    }
}
