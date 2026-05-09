<?php

declare(strict_types=1);

namespace App\Actions\Export;

use App\Models\Equipment;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;

class ExportEquipmentCsv
{
    public const HEADER = [
        'name',
        'type',
        'vendor',
        'model',
        'serial',
        'firmware',
        'asset_tag',
        'site',
        'room',
        'rack',
        'mounted',
        'position_u_start',
        'position_u_height',
        'status',
        'management_ip',
        'description',
    ];

    /**
     * Streams every Equipment row visible to the current tenant scope into a
     * CSV file under storage/app/exports/. Returns the relative path on the
     * local disk, suitable for `Storage::disk('local')->download(...)`.
     */
    public function execute(?int $siteId = null): string
    {
        Storage::disk('local')->makeDirectory('exports');

        $relative = 'exports/equipment-'.now()->format('Ymd-His-').uniqid().'.csv';
        $absolute = Storage::disk('local')->path($relative);

        $csv = Writer::createFromPath($absolute, 'w+');
        $csv->insertOne(self::HEADER);

        Equipment::query()
            ->when($siteId, fn ($q) => $q->whereHas('rack.room', fn ($qq) => $qq->where('site_id', $siteId)))
            ->with('rack.room.site')
            ->lazy()
            ->each(function (Equipment $eq) use ($csv): void {
                $csv->insertOne([
                    $eq->name,
                    $eq->type?->value ?? '',
                    $eq->vendor ?? '',
                    $eq->model ?? '',
                    $eq->serial ?? '',
                    $eq->firmware ?? '',
                    $eq->asset_tag ?? '',
                    $eq->rack?->room?->site?->name ?: '',
                    $eq->rack?->room?->name ?: '',
                    $eq->rack?->name ?: '',
                    $eq->mounted ? 'true' : 'false',
                    $eq->position_u_start !== null ? (string) $eq->position_u_start : '',
                    $eq->position_u_height !== null ? (string) $eq->position_u_height : '',
                    $eq->status?->value ?? '',
                    $eq->management_ip ?? '',
                    $eq->description ?? '',
                ]);
            });

        return $relative;
    }
}
