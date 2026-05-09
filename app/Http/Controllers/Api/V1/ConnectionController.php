<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ConnectionRequest;
use App\Http\Resources\ConnectionResource;
use App\Models\Connection;
use App\Models\NetworkInterface;
use App\Services\ConnectionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ConnectionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Connection::class);

        $query = Connection::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));

        $perPage = min($request->integer('per_page', 50), 200);

        return ConnectionResource::collection($query->paginate($perPage));
    }

    public function store(ConnectionRequest $request, ConnectionService $service): ConnectionResource
    {
        $data = $request->validated();

        $a = NetworkInterface::query()->findOrFail($data['from_interface_id']);
        $b = NetworkInterface::query()->findOrFail($data['to_interface_id']);

        try {
            $conn = $service->connect($a, $b, [
                'cable_type' => $data['cable_type'],
                'cable_length_m' => $data['cable_length_m'] ?? null,
                'cable_label' => $data['cable_label'] ?? null,
                'color' => $data['color'] ?? null,
                'notes' => $data['notes'] ?? null,
                'established_at' => $data['established_at'] ?? null,
            ]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['to_interface_id' => $e->getMessage()]);
        }

        return new ConnectionResource($conn);
    }

    public function show(Connection $connection): ConnectionResource
    {
        $this->authorize('view', $connection);

        return new ConnectionResource($connection);
    }

    public function update(ConnectionRequest $request, Connection $connection): ConnectionResource
    {
        // Update only the metadata fields; endpoint changes go through DELETE+POST.
        $data = collect($request->validated())
            ->only(['cable_type', 'cable_length_m', 'cable_label', 'color', 'status', 'notes', 'established_at'])
            ->all();
        $connection->update($data);

        return new ConnectionResource($connection);
    }

    public function destroy(Connection $connection): Response
    {
        $this->authorize('delete', $connection);
        $connection->delete();

        return response()->noContent();
    }
}
