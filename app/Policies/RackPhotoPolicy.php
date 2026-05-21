<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Concerns\ChecksTenantMembership;

class RackPhotoPolicy
{
    use ChecksTenantMembership;
}
