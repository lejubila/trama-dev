<?php

declare(strict_types=1);

namespace App\Http\Controllers\Export;

use App\Actions\Export\ExportEquipmentCsv;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquipmentCsvController extends Controller
{
    public function __invoke(Request $request, ExportEquipmentCsv $exporter): BinaryFileResponse
    {
        $this->authorize('viewAny', Equipment::class);

        $siteId = $request->integer('site_id') ?: null;
        $relative = $exporter->execute($siteId);

        return response()->download(
            Storage::disk('local')->path($relative),
            'equipment-'.now()->format('Ymd-His').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        )->deleteFileAfterSend(false); // file kept for the cleanup task
    }

    /**
     * Serves a tiny CSV containing only the headers + a single example row so
     * users can fill it in and re-upload.
     */
    public function template(): StreamedResponse
    {
        $rows = [
            ExportEquipmentCsv::HEADER,
            [
                'SW-EXAMPLE-01', 'switch', 'Cisco', 'Catalyst 9300', 'SN12345', '17.9.4', 'AT-001',
                'Sede Milano', 'CED Milano', 'Rack-MI-A1',
                'true', '36', '1', 'active', '10.0.0.10', 'Esempio importazione',
            ],
        ];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
        }, 'equipment-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
