<?php

declare(strict_types=1);

namespace App\Enums;

enum InterfaceVlanMode: string
{
    case None = 'none';
    case Access = 'access';
    case Trunk = 'trunk';
    case Hybrid = 'hybrid';
}
