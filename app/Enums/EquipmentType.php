<?php

declare(strict_types=1);

namespace App\Enums;

enum EquipmentType: string
{
    // Rete
    case Switch = 'switch';
    case Router = 'router';
    case Firewall = 'firewall';
    case AccessPoint = 'access_point';
    case Controller = 'controller';
    case MediaConverter = 'media_converter';

    // Cablaggio passivo
    case PatchPanel = 'patch_panel';
    case WallOutlet = 'wall_outlet';

    // Server & storage
    case Server = 'server';
    case Nas = 'nas';

    // Alimentazione
    case Ups = 'ups';
    case Pdu = 'pdu';

    // Gestione
    case Kvm = 'kvm';

    // Telefonia & comunicazione
    case PhoneSystem = 'phone_system';
    case Intercom = 'intercom';

    // Sicurezza & videosorveglianza
    case AccessControl = 'access_control';
    case Nvr = 'nvr';
    case Camera = 'camera';

    // Postazioni & end-user
    case Computer = 'computer';
    case Notebook = 'notebook';
    case Tv = 'tv';
    case Printer = 'printer';
    case IotDevice = 'iot_device';

    // Altro
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Switch => 'Switch',
            self::Router => 'Router',
            self::Firewall => 'Firewall',
            self::AccessPoint => 'Access Point',
            self::Controller => 'Controller',
            self::MediaConverter => 'Media Converter',
            self::PatchPanel => 'Patch Panel',
            self::WallOutlet => 'Presa a muro',
            self::Server => 'Server',
            self::Nas => 'NAS',
            self::Ups => 'UPS',
            self::Pdu => 'PDU',
            self::Kvm => 'KVM',
            self::PhoneSystem => 'Centralino telefonico',
            self::Intercom => 'Citofono',
            self::AccessControl => 'Controllo accessi',
            self::Nvr => 'NVR',
            self::Camera => 'Telecamera',
            self::Computer => 'Computer',
            self::Notebook => 'Notebook',
            self::Tv => 'TV',
            self::Printer => 'Stampante',
            self::IotDevice => 'Dispositivo IoT',
            self::Other => 'Altro',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Switch => 'cyan',
            self::Router => 'violet',
            self::Firewall => 'red',
            self::AccessPoint => 'emerald',
            self::Controller => 'amber',
            self::MediaConverter => 'fuchsia',
            self::PatchPanel => 'slate',
            self::WallOutlet => 'stone',
            self::Server => 'blue',
            self::Nas => 'teal',
            self::Ups, self::Pdu => 'yellow',
            self::Kvm => 'orange',
            self::PhoneSystem => 'indigo',
            self::Intercom => 'rose',
            self::AccessControl => 'lime',
            self::Nvr => 'sky',
            self::Camera => 'pink',
            self::Computer => 'green',
            self::Notebook => 'lime',
            self::Tv => 'purple',
            self::Printer => 'zinc',
            self::IotDevice => 'neutral',
            self::Other => 'gray',
        };
    }

    public function category(): EquipmentCategory
    {
        return match ($this) {
            self::Switch, self::Router, self::Firewall, self::AccessPoint, self::Controller, self::MediaConverter => EquipmentCategory::Network,
            self::PatchPanel, self::WallOutlet => EquipmentCategory::PassiveCabling,
            self::Server, self::Nas => EquipmentCategory::ServerStorage,
            self::Ups, self::Pdu => EquipmentCategory::Power,
            self::Kvm => EquipmentCategory::Management,
            self::PhoneSystem, self::Intercom => EquipmentCategory::VoiceComms,
            self::AccessControl, self::Nvr, self::Camera => EquipmentCategory::Security,
            self::Computer, self::Notebook, self::Tv, self::Printer, self::IotDevice => EquipmentCategory::EndUser,
            self::Other => EquipmentCategory::Other,
        };
    }

    /**
     * @return array<string, list<self>>
     */
    public static function groupedCases(): array
    {
        $groups = [];
        foreach (self::cases() as $case) {
            $groups[$case->category()->label()][] = $case;
        }

        return $groups;
    }
}
