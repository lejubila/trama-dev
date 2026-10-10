<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Guide\GuideOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GuideOfferController extends Controller
{
    public function __invoke(Request $request): Response
    {
        GuideOffer::dismiss($request->user());

        return response()->noContent();
    }
}
