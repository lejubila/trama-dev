<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Services\TopologyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TopologyController extends Controller
{
    public function __invoke(Request $request, TopologyService $svc): JsonResponse
    {
        $this->authorize('viewAny', Equipment::class);

        $types = $request->input('types');
        if (is_string($types) && $types !== '') {
            $types = array_values(array_filter(array_map('trim', explode(',', $types))));
        }

        $graph = $svc->buildGraph(
            siteId: $request->filled('site_id') ? $request->integer('site_id') : null,
            types: is_array($types) && $types !== [] ? $types : null,
            vlan: $request->filled('vlan') ? $request->integer('vlan') : null,
            status: $request->filled('status') ? (string) $request->string('status') : null,
        );

        return response()->json(['data' => $graph]);
    }
}
