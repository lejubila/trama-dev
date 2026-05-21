<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Equipment;
use App\Models\Rack;

class RackPlacementService
{
    /**
     * Returns the list of U positions currently occupied in the rack on a
     * given side (front | rear). On-top devices are skipped (they don't
     * occupy any U). NULL `position_orient` is treated as 'front' for
     * backwards-compat with older rows.
     *
     * @return list<int>
     */
    public function getOccupiedUnits(Rack $rack, ?Equipment $excluding = null, string $orient = 'front'): array
    {
        $query = $rack->equipment()
            ->where('mounted', true)
            ->where(function ($qq): void {
                $qq->where('on_top', false)->orWhereNull('on_top');
            })
            ->whereNotNull('position_u_start')
            ->whereNotNull('position_u_height')
            ->when($orient === 'rear',
                fn ($q) => $q->where('position_orient', 'rear'),
                fn ($q) => $q->where(function ($qq) {
                    $qq->whereNull('position_orient')->orWhere('position_orient', 'front');
                }),
            );

        if ($excluding !== null) {
            $query->whereKeyNot($excluding->getKey());
        }

        $occupied = [];
        foreach ($query->get() as $eq) {
            for ($u = $eq->position_u_start; $u < $eq->position_u_start + $eq->position_u_height; $u++) {
                $occupied[] = $u;
            }
        }

        sort($occupied);

        return array_values(array_unique($occupied));
    }

    /**
     * Can we place a $heightU unit equipment starting at $startU in $rack on
     * the given side? Multiple devices may share the same U on the same
     * side (rendered side-by-side in lanes), so we only enforce bounds
     * here; the U-overlap check has been intentionally removed.
     *
     * Front and rear are independent regardless, since lane assignment
     * happens per side at render time.
     */
    public function canPlace(Rack $rack, int $startU, int $heightU, ?Equipment $excluding = null, string $orient = 'front'): bool
    {
        if ($startU < 1 || $heightU < 1) {
            return false;
        }
        if ($startU + $heightU - 1 > $rack->height_units) {
            return false;
        }

        return true;
    }

    /**
     * Returns all start-U positions where a $heightU unit equipment would fit
     * on the given side.
     *
     * @return list<int>
     */
    public function findAvailableSlots(Rack $rack, int $heightU, string $orient = 'front'): array
    {
        $slots = [];
        for ($start = 1; $start + $heightU - 1 <= $rack->height_units; $start++) {
            if ($this->canPlace($rack, $start, $heightU, null, $orient)) {
                $slots[] = $start;
            }
        }

        return $slots;
    }
}
