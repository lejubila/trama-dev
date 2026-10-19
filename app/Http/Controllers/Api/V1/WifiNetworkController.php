<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ChecksTenantIds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WifiNetworkRequest;
use App\Http\Resources\WifiNetworkResource;
use App\Models\NetworkInterface;
use App\Models\Site;
use App\Models\WifiNetwork;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class WifiNetworkController extends Controller
{
    use ChecksTenantIds;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WifiNetwork::class);

        $query = WifiNetwork::query()
            ->with('broadcasters')
            ->withCount('associations')
            ->when($request->filled('q'), fn ($q) => $q->where('ssid', 'ilike', '%'.$request->string('q').'%'))
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->integer('site_id')))
            ->orderBy('ssid');

        $perPage = min($request->integer('per_page', 50), 200);

        return WifiNetworkResource::collection($query->paginate($perPage));
    }

    public function store(WifiNetworkRequest $request): WifiNetworkResource
    {
        $data = $request->validated();
        $broadcasters = $this->checkReferences($data);

        $network = DB::transaction(function () use ($data, $broadcasters): WifiNetwork {
            $network = WifiNetwork::create(collect($data)->except('broadcaster_interface_ids')->all());
            $network->broadcasters()->sync($broadcasters ?? []);

            return $network;
        });

        return new WifiNetworkResource($network->load('broadcasters'));
    }

    public function show(WifiNetwork $wifiNetwork): WifiNetworkResource
    {
        $this->authorize('view', $wifiNetwork);

        return new WifiNetworkResource($wifiNetwork->load(['broadcasters', 'associations.clientInterface.equipment'])->loadCount('associations'));
    }

    public function update(WifiNetworkRequest $request, WifiNetwork $wifiNetwork): WifiNetworkResource
    {
        $data = $request->validated();
        $broadcasters = $this->checkReferences($data);

        DB::transaction(function () use ($wifiNetwork, $data, $broadcasters): void {
            $wifiNetwork->update(collect($data)->except('broadcaster_interface_ids')->all());
            if ($broadcasters !== null) {
                $wifiNetwork->broadcasters()->sync($broadcasters);
            }
        });

        return new WifiNetworkResource($wifiNetwork->load('broadcasters'));
    }

    public function destroy(WifiNetwork $wifiNetwork): Response
    {
        $this->authorize('delete', $wifiNetwork);
        $wifiNetwork->delete();

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>|null Broadcaster ids to sync, null when not sent.
     */
    private function checkReferences(array $data): ?array
    {
        if (isset($data['site_id'])) {
            $this->ensureId(Site::query(), (int) $data['site_id'], 'site_id', 'Unknown site');
        }

        if (! array_key_exists('broadcaster_interface_ids', $data)) {
            return null;
        }

        // Same eligibility as the UI: wireless interfaces of access points / controllers.
        return $this->ensureIds(
            NetworkInterface::query()->wifiBroadcasters(),
            $data['broadcaster_interface_ids'] ?? [],
            'broadcaster_interface_ids',
            'Not wireless interfaces of an access point or controller',
        );
    }
}
