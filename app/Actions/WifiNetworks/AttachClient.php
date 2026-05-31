<?php

declare(strict_types=1);

namespace App\Actions\WifiNetworks;

use App\Enums\InterfaceMedia;
use App\Enums\InterfaceType;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\WifiAssociation;
use App\Models\WifiNetwork;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Single-shot transactional join of a client device to a Wi-Fi network.
 *
 * If the caller did not provide an existing client wireless interface, this
 * action creates one (default name "wlan0", auto-incrementing if taken) so
 * the user doesn't have to navigate to the equipment page first. The
 * resulting WifiAssociation row is then created in the same transaction.
 */
class AttachClient
{
    public function execute(
        WifiNetwork $network,
        Equipment $client,
        ?NetworkInterface $existingInterface = null,
        ?NetworkInterface $preferredBroadcaster = null,
    ): WifiAssociation {
        if ((int) $network->tenant_id !== (int) $client->tenant_id) {
            throw new InvalidArgumentException('Network and client belong to different tenants.');
        }

        return DB::transaction(function () use ($network, $client, $existingInterface, $preferredBroadcaster): WifiAssociation {
            $iface = $existingInterface ?? $this->createDefaultWirelessInterface($client);

            if ((int) $iface->equipment_id !== (int) $client->id) {
                throw new InvalidArgumentException('Interface does not belong to client equipment.');
            }
            if ($iface->media !== InterfaceMedia::Wireless) {
                throw new InvalidArgumentException('Client interface must be wireless.');
            }

            return WifiAssociation::create([
                'tenant_id' => $network->tenant_id,
                'wifi_network_id' => $network->id,
                'client_interface_id' => $iface->id,
                'preferred_broadcaster_interface_id' => $preferredBroadcaster?->id,
            ]);
        });
    }

    /**
     * Pick the next available wlanN name on the client equipment and create
     * a minimal wireless interface there. Used when the user picks
     * "associate" without first manually creating an interface.
     */
    private function createDefaultWirelessInterface(Equipment $client): NetworkInterface
    {
        $existingNames = NetworkInterface::query()
            ->where('equipment_id', $client->id)
            ->pluck('name')
            ->all();

        $i = 0;
        do {
            $candidate = 'wlan'.$i;
            $i++;
        } while (in_array($candidate, $existingNames, true));

        return NetworkInterface::create([
            'tenant_id' => $client->tenant_id,
            'equipment_id' => $client->id,
            'name' => $candidate,
            'type' => InterfaceType::Wireless,
            'media' => InterfaceMedia::Wireless,
        ]);
    }
}
