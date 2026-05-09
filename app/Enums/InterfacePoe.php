<?php

declare(strict_types=1);

namespace App\Enums;

enum InterfacePoe: string
{
    case None = 'none';
    /** Power Sourcing Equipment — fornisce PoE (es. switch PoE) */
    case Pse = 'pse';
    /** Powered Device — riceve PoE (es. AP, telefono IP) */
    case Pd = 'pd';
}
