<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ChecksTenantIds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VpnRemoteAccessRequest;
use App\Http\Resources\VpnRemoteAccessResource;
use App\Models\NetworkInterface;
use App\Models\VpnRemoteAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class VpnRemoteAccessController extends Controller
{
    use ChecksTenantIds;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VpnRemoteAccess::class);

        $query = VpnRemoteAccess::query()
            ->with('firewallInterface')
            ->withCount('clients')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'ilike', '%'.$request->string('q').'%'))
            ->when($request->filled('protocol'), fn ($q) => $q->where('protocol', $request->string('protocol')))
            ->orderBy('name');

        $perPage = min($request->integer('per_page', 50), 200);

        return VpnRemoteAccessResource::collection($query->paginate($perPage));
    }

    public function store(VpnRemoteAccessRequest $request): VpnRemoteAccessResource
    {
        $data = $request->validated();
        $this->checkFirewallInterface($data);

        return new VpnRemoteAccessResource(VpnRemoteAccess::create($data)->load('firewallInterface'));
    }

    public function show(VpnRemoteAccess $vpn): VpnRemoteAccessResource
    {
        $this->authorize('view', $vpn);

        return new VpnRemoteAccessResource($vpn->load(['firewallInterface', 'clients.clientInterface.equipment'])->loadCount('clients'));
    }

    public function update(VpnRemoteAccessRequest $request, VpnRemoteAccess $vpn): VpnRemoteAccessResource
    {
        $data = $request->validated();
        $this->checkFirewallInterface($data);

        $vpn->update($data);

        return new VpnRemoteAccessResource($vpn->load('firewallInterface'));
    }

    public function destroy(VpnRemoteAccess $vpn): Response
    {
        $this->authorize('delete', $vpn);
        $vpn->delete();

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function checkFirewallInterface(array $data): void
    {
        if (isset($data['firewall_interface_id'])) {
            $this->ensureId(NetworkInterface::query()->onFirewall(), (int) $data['firewall_interface_id'], 'firewall_interface_id', 'Not an interface of a firewall');
        }
    }
}
