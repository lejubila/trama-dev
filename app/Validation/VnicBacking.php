<?php

declare(strict_types=1);

namespace App\Validation;

use App\Enums\InterfaceType;
use App\Models\Equipment;
use App\Models\NetworkInterface;

/**
 * Rules for `interfaces.backed_by_interface_id`, shared by the web form and
 * the API. A virtual interface is carried by a physical port: on a VM it is
 * a pNIC of the hypervisor host, on any other device one of its own ports
 * (VLAN sub-interfaces).
 */
final class VnicBacking
{
    public const PHYSICAL_TYPES = [InterfaceType::Ethernet, InterfaceType::Fiber, InterfaceType::Wireless];

    /**
     * Equipment whose ports may back the virtual interfaces of $equipment,
     * or null for a VM without a host.
     */
    public static function sourceEquipmentId(Equipment $equipment): ?int
    {
        return $equipment->isVirtualMachine()
            ? $equipment->host_equipment_id
            : (int) $equipment->getKey();
    }

    /**
     * Translated error for an invalid backing choice, null when it is valid.
     */
    public static function error(Equipment $equipment, int $backingId, ?int $selfId = null): ?string
    {
        $expectedEquipmentId = self::sourceEquipmentId($equipment);
        if ($expectedEquipmentId === null) {
            return __('equipment.err_vm_no_host');
        }

        $pnic = NetworkInterface::query()->find($backingId);
        if ($pnic === null || (int) $pnic->equipment_id !== $expectedEquipmentId) {
            return $equipment->isVirtualMachine()
                ? __('equipment.err_backing_not_host')
                : __('equipment.err_backing_not_self');
        }
        if ($selfId !== null && (int) $pnic->id === $selfId) {
            return __('equipment.err_backing_self');
        }
        if (! in_array($pnic->type, self::PHYSICAL_TYPES, true)) {
            return __('equipment.err_backing_not_physical');
        }

        return null;
    }
}
