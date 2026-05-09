<?php

declare(strict_types=1);

namespace App\Enums;

enum ConnectionStatus: string
{
    case Active = 'active';
    case Planned = 'planned';
    case Decommissioned = 'decommissioned';
}
