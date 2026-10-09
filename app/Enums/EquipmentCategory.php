<?php

declare(strict_types=1);

namespace App\Enums;

enum EquipmentCategory: string
{
    case Network = 'network';
    case PassiveCabling = 'passive_cabling';
    case ServerStorage = 'server_storage';
    case Power = 'power';
    case Management = 'management';
    case VoiceComms = 'voice_comms';
    case Security = 'security';
    case EndUser = 'end_user';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Network => __('enums.equipment_category.network'),
            self::PassiveCabling => __('enums.equipment_category.passive_cabling'),
            self::ServerStorage => __('enums.equipment_category.server_storage'),
            self::Power => __('enums.equipment_category.power'),
            self::Management => __('enums.equipment_category.management'),
            self::VoiceComms => __('enums.equipment_category.voice_comms'),
            self::Security => __('enums.equipment_category.security'),
            self::EndUser => __('enums.equipment_category.end_user'),
            self::Other => __('enums.equipment_category.other'),
        };
    }
}
