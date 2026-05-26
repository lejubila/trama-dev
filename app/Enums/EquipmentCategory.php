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
            self::Network => 'Rete',
            self::PassiveCabling => 'Cablaggio passivo',
            self::ServerStorage => 'Server & storage',
            self::Power => 'Alimentazione',
            self::Management => 'Gestione',
            self::VoiceComms => 'Telefonia & comunicazione',
            self::Security => 'Sicurezza & videosorveglianza',
            self::EndUser => 'Postazioni & end-user',
            self::Other => 'Altro',
        };
    }
}
