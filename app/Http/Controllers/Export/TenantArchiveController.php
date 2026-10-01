<?php

declare(strict_types=1);

namespace App\Http\Controllers\Export;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantTransfer\TenantArchiveExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TenantArchiveController extends Controller
{
    public function __invoke(Tenant $tenant, TenantArchiveExporter $exporter): BinaryFileResponse
    {
        $this->authorize('export', $tenant);

        return response()->download(
            $exporter->export($tenant),
            'trama-'.$tenant->slug.'-'.now()->format('Ymd-Hi').'.zip',
            ['Content-Type' => 'application/zip'],
        )->deleteFileAfterSend();
    }
}
