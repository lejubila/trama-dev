<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Guide\GuideTours;
use Illuminate\Http\JsonResponse;

/**
 * Fresh GuideTours::facts() for the browser: the copy in the page is as old
 * as the last navigation, and the user may have added or deleted a device
 * since (Livewire actions do not re-render the layout).
 */
class GuideFactsController extends Controller
{
    public function __invoke(GuideTours $guideTours): JsonResponse
    {
        abort_unless(auth()->user()?->canManageData(), 403);

        return response()->json($guideTours->facts());
    }
}
