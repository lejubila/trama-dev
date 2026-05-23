<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\NetworkInterface;

/**
 * Keeps the front/rear pair of a keystone port in sync. When one half is
 * removed via Eloquent we cascade-delete its sibling so a patch-panel port
 * never ends up half-orphaned; renaming one half propagates to the other.
 */
class NetworkInterfacePairObserver
{
    /**
     * Re-entrancy guard: cascading a delete to the paired row would otherwise
     * trigger this observer again and double-delete.
     *
     * @var array<int, true>
     */
    private static array $deleting = [];

    /**
     * Same as above but for renames, since updating the sibling would
     * recursively fire this hook.
     *
     * @var array<int, true>
     */
    private static array $updating = [];

    public function deleting(NetworkInterface $interface): void
    {
        $pairedId = $interface->paired_interface_id;
        if ($pairedId === null || isset(self::$deleting[$interface->getKey()])) {
            return;
        }

        $paired = NetworkInterface::query()->find($pairedId);
        if ($paired === null) {
            return;
        }

        self::$deleting[$paired->getKey()] = true;
        try {
            $paired->delete();
        } finally {
            unset(self::$deleting[$paired->getKey()]);
        }
    }

    public function updated(NetworkInterface $interface): void
    {
        $pairedId = $interface->paired_interface_id;
        if ($pairedId === null || isset(self::$updating[$interface->getKey()])) {
            return;
        }

        // Only mirror cosmetic attributes that should always match between
        // sides; connection-state fields (mac/ip) and side itself stay local.
        $mirrored = ['name', 'index', 'connector', 'description'];
        $changed = array_intersect($mirrored, array_keys($interface->getChanges()));
        if ($changed === []) {
            return;
        }

        $paired = NetworkInterface::query()->find($pairedId);
        if ($paired === null) {
            return;
        }

        self::$updating[$paired->getKey()] = true;
        try {
            $paired->update($interface->only($changed));
        } finally {
            unset(self::$updating[$paired->getKey()]);
        }
    }
}
