<?php

declare(strict_types=1);

namespace App\Enums;

enum LinkGroupMode: string
{
    case Lacp = 'lacp';
    case Static = 'static';
}
