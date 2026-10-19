<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\InterfaceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InterfaceRequest;
use App\Http\Resources\NetworkInterfaceResource;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Validation\VnicBacking;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class NetworkInterfaceController extends Controller
{
    public function index(Equipment $equipment): AnonymousResourceCollection
    {
        $this->authorize('view', $equipment);

        return NetworkInterfaceResource::collection($equipment->interfaces()->paginate(50));
    }

    public function store(InterfaceRequest $request, Equipment $equipment): NetworkInterfaceResource
    {
        $payload = $request->validated();
        $payload['equipment_id'] = $equipment->getKey();
        $payload = $this->withBacking($payload, $equipment, null);
        $if = NetworkInterface::create($payload);

        return new NetworkInterfaceResource($if);
    }

    public function show(NetworkInterface $interface): NetworkInterfaceResource
    {
        $this->authorize('view', $interface);

        return new NetworkInterfaceResource($interface);
    }

    public function update(InterfaceRequest $request, NetworkInterface $interface): NetworkInterfaceResource
    {
        /** @var Equipment $equipment */
        $equipment = $interface->equipment;
        $interface->update($this->withBacking($request->validated(), $equipment, $interface));

        return new NetworkInterfaceResource($interface);
    }

    public function destroy(NetworkInterface $interface): Response
    {
        $this->authorize('delete', $interface);
        $interface->delete();

        return response()->noContent();
    }

    /**
     * Validates `backed_by_interface_id` against the interface's effective
     * type, and drops a stale backing when the interface stops being virtual.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withBacking(array $payload, Equipment $equipment, ?NetworkInterface $current): array
    {
        $type = isset($payload['type']) ? InterfaceType::from($payload['type']) : $current?->type;

        if ($type !== InterfaceType::Virtual) {
            if (($payload['backed_by_interface_id'] ?? null) !== null) {
                throw ValidationException::withMessages(['backed_by_interface_id' => __('equipment.err_backing_only_virtual')]);
            }
            if ($current?->backed_by_interface_id !== null) {
                $payload['backed_by_interface_id'] = null;
            }

            return $payload;
        }

        $backing = $payload['backed_by_interface_id'] ?? null;
        if ($backing !== null) {
            $error = VnicBacking::error($equipment, (int) $backing, $current?->getKey());
            if ($error !== null) {
                throw ValidationException::withMessages(['backed_by_interface_id' => $error]);
            }
        }

        return $payload;
    }
}
