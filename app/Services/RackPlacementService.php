<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Rack;

class RackPlacementService
{
    /**
     * Bounds check only: several devices may share a U on the same side
     * (rendered side-by-side in lanes), so overlaps are allowed on purpose.
     */
    public function canPlace(Rack $rack, int $startU, int $heightU): bool
    {
        return $startU >= 1 && $heightU >= 1 && $startU + $heightU - 1 <= $rack->height_units;
    }
}
