<?php

declare(strict_types=1);

namespace App\Enums;

enum InterfaceType: string
{
    case Ethernet = 'ethernet';
    case Fiber = 'fiber';
    case Wireless = 'wireless';
    case Console = 'console';
    case Management = 'management';
    case Power = 'power';
    case Keystone = 'keystone';
    case Virtual = 'virtual';
    case Loopback = 'loopback';
}
