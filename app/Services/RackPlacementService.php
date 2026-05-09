<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Equipment;
use App\Models\Rack;

class RackPlacementService
{
    /**
     * Returns the list of U positions currently occupied in the rack by
     * mounted equipment, sorted ascending. Excludes soft-deleted equipment
     * and (optionally) one equipment we're trying to relocate.
     *
     * @return list<int>
     */
    public function getOccupiedUnits(Rack $rack, ?Equipment $excluding = null): array
    {
        $query = $rack->equipment()
            ->where('mounted', true)
            ->whereNotNull('position_u_start')
            ->whereNotNull('position_u_height');

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
     * Can we place a $heightU unit equipment starting at $startU in $rack
     * without overlapping anything (optionally ignoring $excluding)?
     */
    public function canPlace(Rack $rack, int $startU, int $heightU, ?Equipment $excluding = null): bool
    {
        if ($startU < 1 || $heightU < 1) {
            return false;
        }
        if ($startU + $heightU - 1 > $rack->height_units) {
            return false;
        }

        $occupied = $this->getOccupiedUnits($rack, $excluding);
        for ($u = $startU; $u < $startU + $heightU; $u++) {
            if (in_array($u, $occupied, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns all start-U positions where a $heightU unit equipment would fit.
     *
     * @return list<int>
     */
    public function findAvailableSlots(Rack $rack, int $heightU): array
    {
        $slots = [];
        for ($start = 1; $start + $heightU - 1 <= $rack->height_units; $start++) {
            if ($this->canPlace($rack, $start, $heightU)) {
                $slots[] = $start;
            }
        }

        return $slots;
    }
}
