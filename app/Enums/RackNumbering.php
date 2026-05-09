<?php

declare(strict_types=1);

namespace App\Enums;

enum RackNumbering: string
{
    case BottomUp = 'bottom_up';
    case TopDown = 'top_down';
}
