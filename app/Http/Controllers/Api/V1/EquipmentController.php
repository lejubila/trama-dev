<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\EquipmentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EquipmentRequest;
use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Services\RackPlacementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EquipmentController extends Controller
{
    /**
     * @OA\Get(
     *   path="/v1/equipment",
     *   tags={"Equipment"},
     *   summary="Lista dispositivi",
     *   security={{"sanctum":{}}},
     *
     *   @OA\Parameter(ref="#/components/parameters/TenantHeader"),
     *   @OA\Parameter(name="q", in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="type", in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="site_id", in="query", @OA\Schema(type="integer")),
     *   @OA\Parameter(name="room_id", in="query", description="Apparati del locale, nei rack o fuori", @OA\Schema(type="integer")),
     *   @OA\Parameter(name="rack_id", in="query", @OA\Schema(type="integer")),
     *   @OA\Parameter(name="host_equipment_id", in="query", description="VM ospitate da questo hypervisor", @OA\Schema(type="integer")),
     *   @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", maximum=200)),
     *
     *   @OA\Response(response=200, description="Lista paginata")
     * )
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Equipment::class);

        $query = Equipment::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            // A device sits in a room either through its rack or directly (no rack).
            ->when($request->filled('site_id'), fn ($q) => $q->where(fn ($qq) => $qq
                ->whereHas('room', fn ($qr) => $qr->where('site_id', $request->integer('site_id')))
                ->orWhereHas('rack.room', fn ($qr) => $qr->where('site_id', $request->integer('site_id')))))
            ->when($request->filled('room_id'), fn ($q) => $q->where(fn ($qq) => $qq
                ->where('room_id', $request->integer('room_id'))
                ->orWhereHas('rack', fn ($qr) => $qr->where('room_id', $request->integer('room_id')))))
            ->when($request->filled('rack_id'), fn ($q) => $q->where('rack_id', $request->integer('rack_id')))
            ->when($request->filled('host_equipment_id'), fn ($q) => $q->where('host_equipment_id', $request->integer('host_equipment_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($qq) use ($request): void {
                $needle = '%'.$request->string('q').'%';
                $qq->where('name', 'ilike', $needle)
                    ->orWhere('serial', 'ilike', $needle)
                    ->orWhere('model', 'ilike', $needle);
            }));

        $perPage = min($request->integer('per_page', 50), 200);

        return EquipmentResource::collection($query->paginate($perPage));
    }

    public function store(EquipmentRequest $request, RackPlacementService $placement): EquipmentResource
    {
        $data = $this->withRoom($this->withHost($request->validated(), null), null);
        $this->ensurePlacement($data, null, $placement);

        $equipment = Equipment::create($data);

        return new EquipmentResource($equipment);
    }

    public function show(Equipment $equipment): EquipmentResource
    {
        $this->authorize('view', $equipment);

        return new EquipmentResource($equipment->load('interfaces', 'rack'));
    }

    public function update(EquipmentRequest $request, Equipment $equipment, RackPlacementService $placement): EquipmentResource
    {
        $data = $this->withRoom($this->withHost($request->validated(), $equipment), $equipment);
        // Merge current values for fields not in payload to validate placement holistically
        $merged = array_merge($equipment->only(['rack_id', 'mounted', 'position_u_start', 'position_u_height']), $data);
        $this->ensurePlacement($merged, $equipment, $placement);

        DB::transaction(function () use ($equipment, $data): void {
            $hostChanged = $equipment->host_equipment_id !== null
                && array_key_exists('host_equipment_id', $data)
                && $data['host_equipment_id'] !== $equipment->host_equipment_id;

            $equipment->update($data);

            // vNICs carried by the old host's pNICs no longer make sense.
            if ($hostChanged) {
                $equipment->interfaces()->whereNotNull('backed_by_interface_id')->update(['backed_by_interface_id' => null]);
            }
        });

        return new EquipmentResource($equipment);
    }

    public function destroy(Equipment $equipment): Response
    {
        $this->authorize('delete', $equipment);
        $equipment->delete();

        return response()->noContent();
    }

    /**
     * A virtual machine lives on a hypervisor host (required) and is never
     * rack-mounted; any other device has no host.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withHost(array $data, ?Equipment $current): array
    {
        $type = isset($data['type']) ? EquipmentType::from($data['type']) : $current?->type;
        $hostId = array_key_exists('host_equipment_id', $data) ? $data['host_equipment_id'] : $current?->host_equipment_id;

        if ($type !== EquipmentType::VirtualMachine) {
            if (($data['host_equipment_id'] ?? null) !== null) {
                throw ValidationException::withMessages(['host_equipment_id' => __('equipment.err_host_only_vm')]);
            }
            if ($current?->host_equipment_id !== null) {
                $data['host_equipment_id'] = null;
            }

            return $data;
        }

        if ($data['mounted'] ?? false) {
            throw ValidationException::withMessages(['mounted' => __('equipment.err_vm_not_mountable')]);
        }

        $host = $hostId !== null ? Equipment::query()->find($hostId) : null;
        if ($host === null || $host->type !== EquipmentType::Hypervisor) {
            throw ValidationException::withMessages(['host_equipment_id' => __('equipment.err_host_not_hypervisor')]);
        }

        $data['host_equipment_id'] = (int) $host->getKey();
        $data['mounted'] = false;
        $data['rack_id'] = null;
        $data['position_u_start'] = null;
        $data['position_u_height'] = null;
        // Same as the web form: the VM shows up under its host's room in filters.
        if ($current?->room_id === null && ! isset($data['room_id'])) {
            $data['room_id'] = $host->room_id ?? $host->rack?->room_id;
        }

        return $data;
    }

    /**
     * Keeps `room_id` coherent with the rack, as the web form does: a
     * rack-mounted device lives in its rack's room, while a device placed
     * directly in a room (no rack) drops any previous rack position.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withRoom(array $data, ?Equipment $current): array
    {
        $roomGiven = array_key_exists('room_id', $data) && $data['room_id'] !== null;

        if ($roomGiven && Room::query()->find($data['room_id']) === null) {
            throw ValidationException::withMessages(['room_id' => __('equipment.err_room_missing')]);
        }

        $mounted = (bool) ($data['mounted'] ?? $current?->mounted ?? false);
        $rackId = array_key_exists('rack_id', $data) ? $data['rack_id'] : $current?->rack_id;
        $rack = $mounted && $rackId !== null ? Rack::query()->find($rackId) : null;

        if ($roomGiven) {
            $explicitMount = ($data['mounted'] ?? null) === true;
            if ($rack !== null && (int) $data['room_id'] === (int) $rack->room_id) {
                return $data; // Same room as the rack: nothing moves.
            }
            if ($explicitMount) {
                // ensurePlacement reports a missing rack; a different room is a contradiction.
                if ($rack !== null) {
                    throw ValidationException::withMessages(['room_id' => __('equipment.err_room_not_rack')]);
                }

                return $data;
            }

            // Another room, no explicit mounting: the device sits loose in
            // the room and leaves any rack it was in.
            $data['mounted'] = false;
            $data['rack_id'] = null;
            $data['position_u_start'] = null;
            $data['position_u_height'] = null;

            return $data;
        }

        if ($rack !== null) {
            $data['room_id'] = (int) $rack->room_id;
        }

        return $data;
    }

    /**
     * Ensures the requested rack placement is valid; throws a Validation
     * exception with a `position_u_start` error otherwise so the response
     * matches the regular FormRequest error shape.
     *
     * @param  array<string, mixed>  $data
     */
    private function ensurePlacement(array $data, ?Equipment $excluding, RackPlacementService $placement): void
    {
        if (! ($data['mounted'] ?? false)) {
            return;
        }

        $rackId = $data['rack_id'] ?? null;
        $startU = $data['position_u_start'] ?? null;
        $heightU = $data['position_u_height'] ?? null;

        if ($rackId === null || $startU === null || $heightU === null) {
            throw ValidationException::withMessages([
                'position_u_start' => 'rack_id, position_u_start e position_u_height sono obbligatori per un dispositivo montato.',
            ]);
        }

        $rack = Rack::query()->find($rackId);
        if ($rack === null) {
            throw ValidationException::withMessages(['rack_id' => 'Rack inesistente.']);
        }

        if (! $placement->canPlace($rack, (int) $startU, (int) $heightU, $excluding)) {
            throw ValidationException::withMessages([
                'position_u_start' => 'Posizione occupata o fuori dal rack.',
            ]);
        }
    }
}
