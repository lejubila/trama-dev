<?php

declare(strict_types=1);

namespace App\Http\Controllers\Export;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentPdfController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Document $document): BinaryFileResponse
    {
        $this->authorize('view', $document);
        abort_if($document->pdf_path === null, 404);

        $absolute = Storage::disk('public')->path($document->pdf_path);
        abort_unless(is_file($absolute), 404);

        $filename = Str::slug($document->title).'-'.$document->id.'.pdf';

        // The PDF path is stable per document (overwritten on regenerate),
        // so we send no-cache headers to ensure browsers don't serve a stale
        // copy after a regeneration. The view also appends ?v=generated_at
        // as a belt-and-braces cache buster.
        return response()->download(
            $absolute,
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ],
        )->deleteFileAfterSend(false);
    }
}
