<?php

declare(strict_types=1);

namespace App\Enums;

enum VpnProtocol: string
{
    case WireGuard = 'wireguard';
    case OpenVpn = 'openvpn';
    case Ipsec = 'ipsec';
    case L2tp = 'l2tp';
    case Pptp = 'pptp';
    case SslVpn = 'ssl_vpn';
    case Gre = 'gre';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WireGuard => 'WireGuard',
            self::OpenVpn => 'OpenVPN',
            self::Ipsec => 'IPsec',
            self::L2tp => 'L2TP',
            self::Pptp => 'PPTP',
            self::SslVpn => 'SSL VPN',
            self::Gre => 'GRE',
            self::Other => 'Altro',
        };
    }
}
