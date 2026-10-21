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
            // A device sits in a room either through its rack or directly (no rack).
            ->when($siteId, fn ($q) => $q->where(fn ($qq) => $qq
                ->whereHas('room', fn ($qr) => $qr->where('site_id', $siteId))
                ->orWhereHas('rack.room', fn ($qr) => $qr->where('site_id', $siteId))))
            ->with('rack.room.site', 'room.site')
            ->lazy()
            ->each(function (Equipment $eq) use ($csv): void {
                $room = $eq->rack?->room ?? $eq->room;
                $csv->insertOne([
                    $eq->name,
                    $eq->type?->value ?? '',
                    $eq->vendor ?? '',
                    $eq->model ?? '',
                    $eq->serial ?? '',
                    $eq->firmware ?? '',
                    $eq->asset_tag ?? '',
                    $room?->site?->name ?: '',
                    $room?->name ?: '',
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
