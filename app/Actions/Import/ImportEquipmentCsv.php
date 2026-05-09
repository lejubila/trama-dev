<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Models\Equipment;
use App\Models\Import;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\User;
use App\Notifications\ImportCompleted;
use App\Services\RackPlacementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use League\Csv\Reader;
use Throwable;

class ImportEquipmentCsv
{
    public function __construct(private readonly RackPlacementService $placement) {}

    /**
     * Reads a CSV (header row required) and creates Equipment rows in the
     * current tenant. Site/Room/Rack are auto-created from the path columns
     * if they don't exist (matched by name within the tenant). The whole
     * batch runs in a single transaction; if any row fails validation the
     * transaction rolls back unless `$ignoreErrors` is true (then valid rows
     * are kept and errors are reported per-row).
     */
    public function execute(string $absolutePath, ?int $userId = null, bool $ignoreErrors = false): Import
    {
        $tenantId = TenantContext::id();
        if ($tenantId === null) {
            throw new \RuntimeException('TenantContext is not set; refuse to import without a tenant.');
        }

        $result = new ImportResult;

        $import = Import::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => 'equipment',
            'file_path' => $absolutePath,
            'status' => 'pending',
            'summary' => [],
        ]);

        try {
            DB::transaction(function () use ($absolutePath, $result, $ignoreErrors): void {
                $csv = Reader::createFromPath($absolutePath, 'r');
                $csv->setHeaderOffset(0);

                $rowNum = 1; // header is row 1; first data row is 2
                foreach ($csv->getRecords() as $row) {
                    $rowNum++;
                    try {
                        $this->processRow($row, $rowNum, $result);
                    } catch (Throwable $e) {
                        $result->addError($rowNum, [$e->getMessage()]);
                    }
                }

                if ($result->hasErrors() && ! $ignoreErrors) {
                    throw new ImportFailedException;
                }
            });

            $import->update([
                'status' => $result->hasErrors() && $ignoreErrors ? 'completed' : 'completed',
                'summary' => $result->toArray(),
            ]);
        } catch (ImportFailedException) {
            $import->update([
                'status' => 'failed',
                'summary' => $result->toArray(),
            ]);
        }

        $import = $import->fresh() ?? $import;

        // Persistent notification for the user who launched the import.
        if ($userId !== null) {
            $user = User::query()->find($userId);
            $user?->notify(new ImportCompleted($import));
        }

        return $import;
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function processRow(array $row, int $rowNum, ImportResult $result): void
    {
        $data = $this->normalizeRow($row);

        $validator = Validator::make($data, [
            'name' => 'required|string|max:150',
            'type' => 'required|string|in:'.implode(',', array_column(EquipmentType::cases(), 'value')),
            'status' => 'nullable|string|in:'.implode(',', array_column(EquipmentStatus::cases(), 'value')),
            'mounted' => 'nullable|in:true,false,1,0',
            'position_u_start' => 'nullable|integer|min:1|max:60',
            'position_u_height' => 'nullable|integer|min:1|max:60',
            'site' => 'nullable|string|max:150',
            'room' => 'nullable|string|max:150',
            'rack' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            $result->addError($rowNum, $validator->errors()->all());

            return;
        }

        $mounted = in_array(strtolower((string) ($data['mounted'] ?? '')), ['true', '1'], true);

        $rackId = null;
        if ($mounted) {
            if (empty($data['site']) || empty($data['room']) || empty($data['rack'])) {
                $result->addError($rowNum, ['Per dispositivi montati site/room/rack sono obbligatori.']);

                return;
            }

            $site = Site::firstOrCreate(['name' => $data['site']]);
            $room = Room::firstOrCreate(
                ['site_id' => $site->getKey(), 'name' => $data['room']],
                ['name' => $data['room'], 'site_id' => $site->getKey()],
            );
            $rack = Rack::firstOrCreate(
                ['room_id' => $room->getKey(), 'name' => $data['rack']],
                ['room_id' => $room->getKey(), 'name' => $data['rack']],
            );
            // firstOrCreate doesn't re-read DB defaults (height_units=42), so
            // refresh the model so RackPlacementService sees the right height.
            $rack->refresh();
            $rackId = $rack->getKey();

            // U-overlap check honoring placement service rules
            $startU = (int) ($data['position_u_start'] ?? 0);
            $heightU = (int) ($data['position_u_height'] ?? 1);
            if ($startU < 1 || ! $this->placement->canPlace($rack, $startU, $heightU)) {
                $result->addError($rowNum, ["Posizione U{$startU} non disponibile nel rack {$rack->name}."]);

                return;
            }
        }

        Equipment::create([
            'rack_id' => $rackId,
            'name' => $data['name'],
            'type' => EquipmentType::from($data['type']),
            'vendor' => $data['vendor'] ?: null,
            'model' => $data['model'] ?: null,
            'serial' => $data['serial'] ?: null,
            'firmware' => $data['firmware'] ?: null,
            'asset_tag' => $data['asset_tag'] ?: null,
            'mounted' => $mounted,
            'position_u_start' => $mounted ? (int) $data['position_u_start'] : null,
            'position_u_height' => $mounted ? (int) ($data['position_u_height'] ?: 1) : null,
            'status' => $data['status'] !== '' ? EquipmentStatus::from($data['status']) : EquipmentStatus::Active,
            'management_ip' => $data['management_ip'] ?: null,
            'description' => $data['description'] ?: null,
        ]);

        $result->incrementCreated();
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, string>
     */
    private function normalizeRow(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            $out[$k] = is_string($v) ? trim($v) : '';
        }
        // Defaults to avoid missing-key warnings downstream
        foreach (['name', 'type', 'vendor', 'model', 'serial', 'firmware', 'asset_tag', 'site', 'room', 'rack', 'mounted', 'position_u_start', 'position_u_height', 'status', 'management_ip', 'description'] as $k) {
            $out[$k] ??= '';
        }

        return $out;
    }
}
