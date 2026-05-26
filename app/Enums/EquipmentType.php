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
    case WallOutlet = 'wall_outlet';
    case Server = 'server';
    case Ups = 'ups';
    case Pdu = 'pdu';
    case MediaConverter = 'media_converter';
    case Nas = 'nas';
    case Kvm = 'kvm';
    case PhoneSystem = 'phone_system';
    case AccessControl = 'access_control';
    case Nvr = 'nvr';
    case Camera = 'camera';
    case Intercom = 'intercom';
    case Tv = 'tv';
    case Computer = 'computer';
    case Notebook = 'notebook';
    case IotDevice = 'iot_device';
    case Printer = 'printer';
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
            self::WallOutlet => 'Presa a muro',
            self::Server => 'Server',
            self::Ups => 'UPS',
            self::Pdu => 'PDU',
            self::MediaConverter => 'Media Converter',
            self::Nas => 'NAS',
            self::Kvm => 'KVM',
            self::PhoneSystem => 'Centralino telefonico',
            self::AccessControl => 'Controllo accessi',
            self::Nvr => 'NVR',
            self::Camera => 'Telecamera',
            self::Intercom => 'Citofono',
            self::Tv => 'TV',
            self::Computer => 'Computer',
            self::Notebook => 'Notebook',
            self::IotDevice => 'Dispositivo IoT',
            self::Printer => 'Stampante',
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
            self::WallOutlet => 'stone',
            self::Server => 'blue',
            self::Ups, self::Pdu => 'yellow',
            self::MediaConverter => 'fuchsia',
            self::Nas => 'teal',
            self::Kvm => 'orange',
            self::PhoneSystem => 'indigo',
            self::AccessControl => 'lime',
            self::Nvr => 'sky',
            self::Camera => 'pink',
            self::Intercom => 'rose',
            self::Tv => 'purple',
            self::Computer => 'green',
            self::Notebook => 'lime',
            self::IotDevice => 'neutral',
            self::Printer => 'zinc',
            self::Other => 'gray',
        };
    }
}
