<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ChecksTenantIds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VpnSiteToSiteRequest;
use App\Http\Resources\VpnSiteToSiteResource;
use App\Models\NetworkInterface;
use App\Models\VpnSiteToSite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class VpnSiteToSiteController extends Controller
{
    use ChecksTenantIds;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VpnSiteToSite::class);

        $query = VpnSiteToSite::query()
            ->with(['endpointAInterface', 'endpointBInterface'])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'ilike', '%'.$request->string('q').'%'))
            ->when($request->filled('protocol'), fn ($q) => $q->where('protocol', $request->string('protocol')))
            ->orderBy('name');

        $perPage = min($request->integer('per_page', 50), 200);

        return VpnSiteToSiteResource::collection($query->paginate($perPage));
    }

    public function store(VpnSiteToSiteRequest $request): VpnSiteToSiteResource
    {
        $data = $request->validated();
        $this->checkEndpoints($data);

        return new VpnSiteToSiteResource(VpnSiteToSite::create($data)->load(['endpointAInterface', 'endpointBInterface']));
    }

    public function show(VpnSiteToSite $vpn): VpnSiteToSiteResource
    {
        $this->authorize('view', $vpn);

        return new VpnSiteToSiteResource($vpn->load(['endpointAInterface', 'endpointBInterface']));
    }

    public function update(VpnSiteToSiteRequest $request, VpnSiteToSite $vpn): VpnSiteToSiteResource
    {
        $data = $request->validated();
        $this->checkEndpoints(array_merge($vpn->only(['endpoint_a_interface_id', 'endpoint_b_interface_id']), $data));

        $vpn->update($data);

        return new VpnSiteToSiteResource($vpn->load(['endpointAInterface', 'endpointBInterface']));
    }

    public function destroy(VpnSiteToSite $vpn): Response
    {
        $this->authorize('delete', $vpn);
        $vpn->delete();

        return response()->noContent();
    }

    /**
     * Both tunnel ends must be distinct firewall interfaces, as in the UI.
     *
     * @param  array<string, mixed>  $data
     */
    private function checkEndpoints(array $data): void
    {
        foreach (['endpoint_a_interface_id', 'endpoint_b_interface_id'] as $field) {
            $this->ensureId(NetworkInterface::query()->onFirewall(), (int) $data[$field], $field, 'Not an interface of a firewall');
        }

        if ((int) $data['endpoint_a_interface_id'] === (int) $data['endpoint_b_interface_id']) {
            throw ValidationException::withMessages(['endpoint_b_interface_id' => 'The two endpoints must be different interfaces']);
        }
    }
}
