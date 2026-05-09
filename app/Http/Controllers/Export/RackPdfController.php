<?php

declare(strict_types=1);

namespace App\Http\Controllers\Export;

use App\Http\Controllers\Controller;
use App\Models\Rack;
use App\Services\Export\PdfExporter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RackPdfController extends Controller
{
    public function __invoke(Rack $rack, PdfExporter $pdf): BinaryFileResponse
    {
        $this->authorize('view', $rack);

        $relative = $pdf->rackPdf($rack);

        return response()->download(
            Storage::disk('local')->path($relative),
            sprintf('rack-%s.pdf', Str::slug($rack->name)),
            ['Content-Type' => 'application/pdf'],
        )->deleteFileAfterSend(false);
    }
}
