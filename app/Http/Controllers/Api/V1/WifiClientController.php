<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\WifiNetworks\AttachClient;
use App\Enums\InterfaceMedia;
use App\Http\Controllers\Api\V1\Concerns\ChecksTenantIds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AttachClientRequest;
use App\Http\Resources\WifiClientResource;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\WifiAssociation;
use App\Models\WifiNetwork;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Client devices associated with a Wi-Fi network.
 */
class WifiClientController extends Controller
{
    use ChecksTenantIds;

    public function index(WifiNetwork $wifiNetwork): AnonymousResourceCollection
    {
        $this->authorize('view', $wifiNetwork);

        return WifiClientResource::collection(
            $wifiNetwork->associations()->with('clientInterface.equipment')->orderBy('id')->get(),
        );
    }

    public function store(AttachClientRequest $request, WifiNetwork $wifiNetwork, AttachClient $action): WifiClientResource
    {
        $data = $request->validated();

        /** @var Equipment $client */
        $client = Equipment::query()->findOr((int) $data['equipment_id'], fn () => throw ValidationException::withMessages(['equipment_id' => 'Unknown equipment']));

        $alreadyAttached = $wifiNetwork->associations()
            ->whereHas('clientInterface', fn ($q) => $q->where('equipment_id', $client->getKey()))
            ->exists();
        if ($alreadyAttached) {
            throw ValidationException::withMessages(['equipment_id' => 'Equipment already associated with this network']);
        }

        $iface = null;
        if (isset($data['interface_id'])) {
            $iface = NetworkInterface::query()
                ->where('equipment_id', $client->getKey())
                ->where('media', InterfaceMedia::Wireless->value)
                ->findOr((int) $data['interface_id'], fn () => throw ValidationException::withMessages(['interface_id' => 'Not a wireless interface of this equipment']));
        }

        $preferred = null;
        if (isset($data['preferred_broadcaster_interface_id'])) {
            $preferred = $wifiNetwork->broadcasters()
                ->findOr((int) $data['preferred_broadcaster_interface_id'], fn () => throw ValidationException::withMessages(['preferred_broadcaster_interface_id' => 'Not a broadcaster of this network']));
        }

        try {
            $association = $action->execute($wifiNetwork, $client, $iface, $preferred);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['equipment_id' => $e->getMessage()]);
        }

        return new WifiClientResource($association->load('clientInterface.equipment'));
    }

    public function destroy(WifiNetwork $wifiNetwork, WifiAssociation $client): Response
    {
        $this->authorize('update', $wifiNetwork);
        abort_unless((int) $client->wifi_network_id === (int) $wifiNetwork->getKey(), 404);

        $client->delete();

        return response()->noContent();
    }
}
