<?php

declare(strict_types=1);

namespace App\Actions\Equipment;

use App\Models\Equipment;
use App\Models\NetworkInterface;
use Illuminate\Support\Facades\DB;

/**
 * Support for the "Duplica" row action of the equipment index: picks the
 * progressive name of the copy and clones the source's interfaces onto the
 * new device. Connections (and Wi-Fi/VPN attachments) are deliberately NOT
 * copied: they link two devices, and a fresh copy starts uncabled.
 */
class DuplicateEquipment
{
    /**
     * "SW-Core" → "SW-Core 2"; if "SW-Core 2" already exists → "SW-Core 3".
     * A source already ending in " N" shares the same base, so duplicating
     * "SW-Core 2" yields "SW-Core 3" instead of "SW-Core 2 2".
     */
    public function nextName(Equipment $source): string
    {
        $base = preg_replace('/ \d+$/', '', $source->name) ?: $source->name;

        $max = 1;
        $names = Equipment::query()
            ->where(fn ($q) => $q->where('name', $base)->orWhere('name', 'like', addcslashes($base, '%_\\').' %'))
            ->pluck('name');

        foreach ($names as $name) {
            if (preg_match('/^'.preg_quote($base, '/').' (\d+)$/', (string) $name, $m) === 1) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $base.' '.($max + 1);
    }

    /**
     * Clones every interface of $source onto $target and returns how many
     * were copied. MAC and IP are cleared because they identify the physical
     * unit; pairing/backing references pointing inside the source device are
     * remapped onto the copies, those pointing elsewhere (vNIC → hypervisor
     * pNIC) are kept as-is.
     */
    public function copyInterfaces(Equipment $source, Equipment $target): int
    {
        return DB::transaction(function () use ($source, $target): int {
            $originals = $source->interfaces()->get();

            /** @var array<int, NetworkInterface> $copies old id => copy */
            $copies = [];
            foreach ($originals as $original) {
                $copy = $original->replicate();
                $copy->fill([
                    'equipment_id' => $target->getKey(),
                    'mac_address' => null,
                    'ip_address' => null,
                    'paired_interface_id' => null,
                    'backed_by_interface_id' => null,
                ]);
                $copy->save();
                $copies[$original->getKey()] = $copy;
            }

            // Second pass: references can only be remapped once every copy
            // has its own id.
            foreach ($originals as $original) {
                $copy = $copies[$original->getKey()];
                $paired = $original->paired_interface_id;
                $backing = $original->backed_by_interface_id;

                $copy->paired_interface_id = $paired !== null && isset($copies[$paired])
                    ? $copies[$paired]->getKey()
                    : null;
                $copy->backed_by_interface_id = $backing !== null && isset($copies[$backing])
                    ? $copies[$backing]->getKey()
                    : $backing;

                if ($copy->isDirty()) {
                    $copy->save();
                }
            }

            return count($copies);
        });
    }
}
