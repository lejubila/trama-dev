<?php

declare(strict_types=1);

namespace App\Actions\Vpn;

use App\Enums\InterfaceMedia;
use App\Enums\InterfaceType;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\VpnRemoteAccess;
use App\Models\VpnRemoteAccessClient;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Attach a client equipment to a remote-access VPN.
 *
 * Mirrors the Wi-Fi AttachClient flow: if the caller did not provide an
 * existing virtual interface on the client, the action creates a default
 * one (vpn0 / vpn1 / …) so the user doesn't have to step into the
 * equipment page first.
 */
class AttachRemoteClient
{
    public function execute(
        VpnRemoteAccess $vpn,
        Equipment $client,
        ?NetworkInterface $existingInterface = null,
        ?string $username = null,
    ): VpnRemoteAccessClient {
        if ((int) $vpn->tenant_id !== (int) $client->tenant_id) {
            throw new InvalidArgumentException('VPN and client belong to different tenants.');
        }

        return DB::transaction(function () use ($vpn, $client, $existingInterface, $username): VpnRemoteAccessClient {
            $iface = $existingInterface ?? $this->createDefaultVirtualInterface($client);

            if ((int) $iface->equipment_id !== (int) $client->id) {
                throw new InvalidArgumentException('Interface does not belong to client equipment.');
            }
            if ($iface->media !== InterfaceMedia::Virtual) {
                throw new InvalidArgumentException('Client interface must be virtual for a VPN association.');
            }

            return VpnRemoteAccessClient::create([
                'tenant_id' => $vpn->tenant_id,
                'vpn_remote_access_id' => $vpn->id,
                'client_interface_id' => $iface->id,
                'username' => $username,
            ]);
        });
    }

    private function createDefaultVirtualInterface(Equipment $client): NetworkInterface
    {
        $existingNames = NetworkInterface::query()
            ->where('equipment_id', $client->id)
            ->pluck('name')
            ->all();

        $i = 0;
        do {
            $candidate = 'vpn'.$i;
            $i++;
        } while (in_array($candidate, $existingNames, true));

        return NetworkInterface::create([
            'tenant_id' => $client->tenant_id,
            'equipment_id' => $client->id,
            'name' => $candidate,
            'type' => InterfaceType::Virtual,
            'media' => InterfaceMedia::Virtual,
        ]);
    }
}
