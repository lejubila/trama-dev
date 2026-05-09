<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InterfaceRequest;
use App\Http\Resources\NetworkInterfaceResource;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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
        $interface->update($request->validated());

        return new NetworkInterfaceResource($interface);
    }

    public function destroy(NetworkInterface $interface): Response
    {
        $this->authorize('delete', $interface);
        $interface->delete();

        return response()->noContent();
    }
}
