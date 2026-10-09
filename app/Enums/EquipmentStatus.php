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
            self::Active => __('enums.equipment_status.active'),
            self::Inactive => __('enums.equipment_status.inactive'),
            self::Maintenance => __('enums.equipment_status.maintenance'),
            self::Decommissioned => __('enums.equipment_status.decommissioned'),
        };
    }
}
