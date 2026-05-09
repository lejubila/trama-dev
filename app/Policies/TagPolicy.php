<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Concerns\ChecksTenantMembership;

class TagPolicy
{
    use ChecksTenantMembership;
}
