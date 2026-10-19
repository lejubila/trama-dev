<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Vpn\AttachRemoteClient;
use App\Enums\InterfaceMedia;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AttachClientRequest;
use App\Http\Resources\VpnRemoteClientResource;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\VpnRemoteAccess;
use App\Models\VpnRemoteAccessClient;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Client devices connected to a remote-access VPN.
 */
class VpnRemoteClientController extends Controller
{
    public function index(VpnRemoteAccess $vpn): AnonymousResourceCollection
    {
        $this->authorize('view', $vpn);

        return VpnRemoteClientResource::collection(
            $vpn->clients()->with('clientInterface.equipment')->orderBy('id')->get(),
        );
    }

    public function store(AttachClientRequest $request, VpnRemoteAccess $vpn, AttachRemoteClient $action): VpnRemoteClientResource
    {
        $data = $request->validated();

        /** @var Equipment $client */
        $client = Equipment::query()->findOr((int) $data['equipment_id'], fn () => throw ValidationException::withMessages(['equipment_id' => 'Unknown equipment']));

        $alreadyAttached = $vpn->clients()
            ->whereHas('clientInterface', fn ($q) => $q->where('equipment_id', $client->getKey()))
            ->exists();
        if ($alreadyAttached) {
            throw ValidationException::withMessages(['equipment_id' => 'Equipment already connected to this VPN']);
        }

        $iface = null;
        if (isset($data['interface_id'])) {
            $iface = NetworkInterface::query()
                ->where('equipment_id', $client->getKey())
                ->where('media', InterfaceMedia::Virtual->value)
                ->findOr((int) $data['interface_id'], fn () => throw ValidationException::withMessages(['interface_id' => 'Not a virtual interface of this equipment']));
        }

        try {
            $row = $action->execute($vpn, $client, $iface, $data['username'] ?? null);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['equipment_id' => $e->getMessage()]);
        }

        return new VpnRemoteClientResource($row->load('clientInterface.equipment'));
    }

    public function destroy(VpnRemoteAccess $vpn, VpnRemoteAccessClient $client): Response
    {
        $this->authorize('update', $vpn);
        abort_unless((int) $client->vpn_remote_access_id === (int) $vpn->getKey(), 404);

        $client->delete();

        return response()->noContent();
    }
}
