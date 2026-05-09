<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConnectionStatus;
use App\Models\Connection;
use App\Models\NetworkInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConnectionService
{
    /**
     * Wire two interfaces with a cable. Validates:
     *  - same tenant on both endpoints
     *  - distinct interfaces (no self-connection)
     *  - both currently free of an active connection
     *
     * The DB-level partial unique indexes (connections_{from,to}_active_unique)
     * back up the third check for the rare race condition window.
     *
     * @param  array{cable_type: string, cable_length_m?: float|null, cable_label?: string|null, color?: string|null, notes?: string|null, established_at?: string|null}  $cable
     */
    public function connect(NetworkInterface $a, NetworkInterface $b, array $cable): Connection
    {
        if ($a->getKey() === $b->getKey()) {
            throw new InvalidArgumentException('Cannot connect an interface to itself.');
        }

        if ($a->tenant_id !== $b->tenant_id) {
            throw new InvalidArgumentException('Endpoints belong to different tenants.');
        }

        return DB::transaction(function () use ($a, $b, $cable): Connection {
            if ($a->activeConnection() !== null || $b->activeConnection() !== null) {
                throw new InvalidArgumentException('At least one endpoint already has an active connection.');
            }

            return Connection::create([
                'tenant_id' => $a->tenant_id,
                'from_interface_id' => $a->getKey(),
                'to_interface_id' => $b->getKey(),
                'cable_type' => $cable['cable_type'],
                'cable_length_m' => $cable['cable_length_m'] ?? null,
                'cable_label' => $cable['cable_label'] ?? null,
                'color' => $cable['color'] ?? null,
                'status' => ConnectionStatus::Active,
                'notes' => $cable['notes'] ?? null,
                'established_at' => $cable['established_at'] ?? null,
            ]);
        });
    }
}
