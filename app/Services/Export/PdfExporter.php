<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Rack;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

/**
 * Renders printable artifacts via headless Chromium (Spatie Browsershot 5.x).
 *
 * We pass the rendered Blade HTML directly to Browsershot::html() instead of
 * a URL. This avoids the round trip of having Browsershot fetch a signed
 * /print route inside the same Docker network — fewer moving parts, no
 * authentication shim. The trade-off is that any external assets referenced
 * by the HTML (CSS bundles, fonts, images) need absolute URLs reachable
 * from headless Chromium; for the rack page we render everything inline.
 */
class PdfExporter
{
    public function rackPdf(Rack $rack): string
    {
        $html = View::make('exports.rack-print', [
            'rack' => $rack->load('room.site'),
        ])->render();

        Storage::disk('local')->makeDirectory('exports');

        $relative = sprintf('exports/rack-%d-%s.pdf', $rack->getKey(), now()->format('Ymd-His'));
        $absolute = Storage::disk('local')->path($relative);

        $shot = Browsershot::html($html)
            ->format('A4')
            ->showBackground()
            ->margins(10, 10, 10, 10)
            ->waitUntilNetworkIdle();

        $chromePath = config('services.browsershot.chrome_path');
        if (is_string($chromePath) && $chromePath !== '') {
            $shot->setChromePath($chromePath);
        }
        $nodeBinary = config('services.browsershot.node_binary');
        if (is_string($nodeBinary) && $nodeBinary !== '') {
            $shot->setNodeBinary($nodeBinary);
        }

        // The Docker app container runs Chromium without a sandbox; otherwise
        // the binary refuses to start as a non-root user.
        $shot->noSandbox();

        $shot->save($absolute);

        return $relative;
    }
}
