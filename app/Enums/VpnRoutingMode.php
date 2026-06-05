<?php

declare(strict_types=1);

namespace App\Enums;

enum VpnRoutingMode: string
{
    case Routed = 'routed';
    case Bridged = 'bridged';

    public function label(): string
    {
        return match ($this) {
            self::Routed => 'Routed',
            self::Bridged => 'Bridged',
        };
    }
}
