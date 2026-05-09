<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RackRequest;
use App\Http\Resources\RackResource;
use App\Models\Rack;
use App\Models\Room;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RackController extends Controller
{
    public function index(Room $room): AnonymousResourceCollection
    {
        $this->authorize('view', $room);

        return RackResource::collection($room->racks()->paginate(50));
    }

    public function store(RackRequest $request, Room $room): RackResource
    {
        $payload = $request->validated();
        $payload['room_id'] = $room->getKey();
        $rack = Rack::create($payload);

        return new RackResource($rack);
    }

    public function show(Rack $rack): RackResource
    {
        $this->authorize('view', $rack);

        return new RackResource($rack);
    }

    public function update(RackRequest $request, Rack $rack): RackResource
    {
        $rack->update($request->validated());

        return new RackResource($rack);
    }

    public function destroy(Rack $rack): Response
    {
        $this->authorize('delete', $rack);
        $rack->delete();

        return response()->noContent();
    }
}
