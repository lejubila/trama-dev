<?php

declare(strict_types=1);

namespace App\Enums;

enum InterfaceMedia: string
{
    case Copper = 'copper';
    case Fiber = 'fiber';
    case Wireless = 'wireless';
    case Virtual = 'virtual';
}
