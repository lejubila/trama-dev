<?php

declare(strict_types=1);

namespace App\Enums;

enum EquipmentType: string
{
    case Switch = 'switch';
    case Router = 'router';
    case Firewall = 'firewall';
    case AccessPoint = 'access_point';
    case Controller = 'controller';
    case PatchPanel = 'patch_panel';
    case Server = 'server';
    case Ups = 'ups';
    case Pdu = 'pdu';
    case MediaConverter = 'media_converter';
    case Nas = 'nas';
    case Kvm = 'kvm';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Switch => 'Switch',
            self::Router => 'Router',
            self::Firewall => 'Firewall',
            self::AccessPoint => 'Access Point',
            self::Controller => 'Controller',
            self::PatchPanel => 'Patch Panel',
            self::Server => 'Server',
            self::Ups => 'UPS',
            self::Pdu => 'PDU',
            self::MediaConverter => 'Media Converter',
            self::Nas => 'NAS',
            self::Kvm => 'KVM',
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
            self::PatchPanel => 'slate',
            self::Server => 'blue',
            self::Ups, self::Pdu => 'yellow',
            self::MediaConverter => 'fuchsia',
            self::Nas => 'teal',
            self::Kvm => 'orange',
            self::Other => 'gray',
        };
    }
}
