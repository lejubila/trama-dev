<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EquipmentRequest;
use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use App\Models\Rack;
use App\Services\RackPlacementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
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
     *   @OA\Parameter(name="rack_id", in="query", @OA\Schema(type="integer")),
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
            ->when($request->filled('site_id'), fn ($q) => $q->whereHas('rack.room', fn ($qq) => $qq->where('site_id', $request->integer('site_id'))))
            ->when($request->filled('rack_id'), fn ($q) => $q->where('rack_id', $request->integer('rack_id')))
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
        $data = $request->validated();
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
        $data = $request->validated();
        // Merge current values for fields not in payload to validate placement holistically
        $merged = array_merge($equipment->only(['rack_id', 'mounted', 'position_u_start', 'position_u_height']), $data);
        $this->ensurePlacement($merged, $equipment, $placement);

        $equipment->update($data);

        return new EquipmentResource($equipment);
    }

    public function destroy(Equipment $equipment): Response
    {
        $this->authorize('delete', $equipment);
        $equipment->delete();

        return response()->noContent();
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
