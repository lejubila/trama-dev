<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SiteRequest;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SiteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Site::class);

        $query = Site::query()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'ilike', '%'.$request->string('q').'%'));

        $perPage = min($request->integer('per_page', 50), 200);

        return SiteResource::collection($query->paginate($perPage));
    }

    public function store(SiteRequest $request): SiteResource
    {
        $site = Site::create($request->validated());

        return new SiteResource($site);
    }

    public function show(Site $site): SiteResource
    {
        $this->authorize('view', $site);

        return new SiteResource($site);
    }

    public function update(SiteRequest $request, Site $site): SiteResource
    {
        $site->update($request->validated());

        return new SiteResource($site);
    }

    public function destroy(Site $site): Response
    {
        $this->authorize('delete', $site);
        $site->delete();

        return response()->noContent();
    }
}
