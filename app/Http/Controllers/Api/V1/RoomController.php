<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Models\Site;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RoomController extends Controller
{
    public function index(Site $site): AnonymousResourceCollection
    {
        $this->authorize('view', $site);

        return RoomResource::collection($site->rooms()->paginate(50));
    }

    public function store(RoomRequest $request, Site $site): RoomResource
    {
        $payload = $request->validated();
        $payload['site_id'] = $site->getKey();
        $room = Room::create($payload);

        return new RoomResource($room);
    }

    public function show(Site $site, Room $room): RoomResource
    {
        $this->authorize('view', $room);
        abort_unless($room->site_id === $site->getKey(), 404);

        return new RoomResource($room);
    }

    public function update(RoomRequest $request, Site $site, Room $room): RoomResource
    {
        abort_unless($room->site_id === $site->getKey(), 404);
        $room->update($request->validated());

        return new RoomResource($room);
    }

    public function destroy(Site $site, Room $room): Response
    {
        $this->authorize('delete', $room);
        abort_unless($room->site_id === $site->getKey(), 404);
        $room->delete();

        return response()->noContent();
    }
}
