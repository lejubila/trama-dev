<?php

declare(strict_types=1);

namespace App\Actions\Interfaces;

use App\Enums\EquipmentType;
use App\Enums\InterfaceSide;
use App\Enums\InterfaceType;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates the two NetworkInterface rows that together represent a single
 * keystone port on a patch panel or wall outlet: a `front` and a `rear`
 * cross-linked via `paired_interface_id`. The two rows share descriptive
 * attributes (name/index/connector/etc.) but each has its own connection
 * surface so a port can be cabled on both sides independently.
 *
 * @phpstan-type Payload array{
 *   name: string,
 *   index?: int|null,
 *   connector?: string|null,
 *   description?: string|null,
 *   status?: string|null,
 * }
 */
class CreateKeystonePair
{
    /**
     * @param  Payload  $payload
     * @return array{0: NetworkInterface, 1: NetworkInterface} [front, rear]
     */
    public function execute(Equipment $equipment, array $payload): array
    {
        $type = $equipment->type;
        if (! in_array($type, [EquipmentType::PatchPanel, EquipmentType::WallOutlet], true)) {
            throw new InvalidArgumentException('Keystone pairs can only be created on patch panels or wall outlets.');
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Port name is required.');
        }

        $base = [
            'equipment_id' => $equipment->getKey(),
            'name' => $name,
            'type' => InterfaceType::Keystone,
            'index' => $payload['index'] ?? 0,
            'media' => 'copper',
            'connector' => $payload['connector'] ?? null,
            'status' => $payload['status'] ?? 'unknown',
            'poe' => 'none',
            'description' => $payload['description'] ?? null,
        ];

        return DB::transaction(function () use ($base): array {
            $front = NetworkInterface::create($base + ['side' => InterfaceSide::Front]);
            $rear = NetworkInterface::create($base + ['side' => InterfaceSide::Rear]);

            $front->update(['paired_interface_id' => $rear->getKey()]);
            $rear->update(['paired_interface_id' => $front->getKey()]);

            return [$front->refresh(), $rear->refresh()];
        });
    }
}
