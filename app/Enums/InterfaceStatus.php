<?php

declare(strict_types=1);

namespace App\Enums;

enum InterfaceStatus: string
{
    case Up = 'up';
    case Down = 'down';
    case AdminDown = 'admin_down';
    case Unknown = 'unknown';
}
