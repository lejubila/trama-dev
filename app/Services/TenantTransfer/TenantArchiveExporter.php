<?php

declare(strict_types=1);

namespace App\Services\TenantTransfer;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Packs every piece of a tenant's data into a zip: `data.json` plus the
 * referenced files under `files/<original path>`. Reads go through the
 * query builder with an explicit tenant_id filter, so the export doesn't
 * depend on which tenant is active in the session.
 */
class TenantArchiveExporter
{
    /**
     * @return string absolute path of a temporary zip; the caller deletes it
     */
    public function export(Tenant $tenant): string
    {
        $tenantId = (int) $tenant->getKey();
        $data = [
            'format' => TenantArchiveSchema::FORMAT,
            'version' => TenantArchiveSchema::VERSION,
            'exported_at' => now()->toIso8601String(),
            'tenant' => [
                'id' => $tenantId,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'domain' => $tenant->domain,
                'settings' => is_array($tenant->settings) ? $tenant->settings : [],
            ],
        ];

        /** @var array<string, array<int, true>> $exportedIds section => set of ids */
        $exportedIds = [];
        $files = [];

        foreach (TenantArchiveSchema::sections() as $section => $def) {
            $rows = DB::table($def['table'])
                ->where('tenant_id', $tenantId)
                ->when($def['soft_deletes'] ?? false, fn ($q) => $q->whereNull('deleted_at'))
                ->orderBy('id')
                ->get();

            $out = [];
            foreach ($rows as $row) {
                $row = (array) $row;
                if (! $this->resolveRefs($row, $def['refs'] ?? [], $exportedIds)) {
                    // Parent was soft-deleted (e.g. ports of a deleted device): skip.
                    continue;
                }
                foreach ($def['json'] ?? [] as $col) {
                    if (isset($row[$col]) && is_string($row[$col])) {
                        $row[$col] = json_decode($row[$col], true);
                    }
                }
                foreach ($def['files'] ?? [] as $col) {
                    if (! empty($row[$col]) && Storage::disk('public')->exists($row[$col])) {
                        $files[$row[$col]] = true;
                    } else {
                        $row[$col] = null;
                    }
                }
                // The original id stays in the file: it's the key the importer remaps.
                foreach (array_diff(TenantArchiveSchema::SKIPPED_COLUMNS, ['id']) as $col) {
                    unset($row[$col]);
                }
                $row['id'] = (int) $row['id'];
                $out[] = $row;
            }

            $exportedIds[$section] = array_fill_keys(array_map(fn ($r) => (int) $r['id'], $out), true);

            // Self-references are only known once the whole section is in.
            foreach ($def['deferred'] ?? [] as $col => $target) {
                foreach ($out as &$row) {
                    if ($row[$col] !== null && ! isset($exportedIds[$target][(int) $row[$col]])) {
                        $row[$col] = null;
                    }
                }
                unset($row);
            }

            $data[$section] = $out;
        }

        $data['link_groups'] = $this->withLinkGroupMembers($data['link_groups'], $exportedIds['connections']);
        $data['wifi_networks'] = $this->withBroadcasters($data['wifi_networks'], $exportedIds['interfaces']);
        $data['taggables'] = $this->taggables($exportedIds);

        $data['counts'] = [];
        foreach (array_keys(TenantArchiveSchema::labels()) as $section) {
            $data['counts'][$section] = count($data[$section]);
        }
        $data['counts']['files'] = count($files);

        return $this->writeZip($data, array_keys($files));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array{0: string, 1: bool}>  $refs
     * @param  array<string, array<int, true>>  $exportedIds
     */
    private function resolveRefs(array &$row, array $refs, array $exportedIds): bool
    {
        foreach ($refs as $col => [$target, $required]) {
            if ($row[$col] === null || isset($exportedIds[$target][(int) $row[$col]])) {
                continue;
            }
            if ($required) {
                return false;
            }
            $row[$col] = null;
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @param  array<int, true>  $connectionIds
     * @return list<array<string, mixed>>
     */
    private function withLinkGroupMembers(array $groups, array $connectionIds): array
    {
        $members = DB::table('link_group_connection')
            ->whereIn('link_group_id', array_column($groups, 'id'))
            ->get()
            ->groupBy('link_group_id');

        foreach ($groups as &$group) {
            $group['connection_ids'] = ($members[$group['id']] ?? collect())
                ->pluck('connection_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => isset($connectionIds[$id]))
                ->values()
                ->all();
        }

        return $groups;
    }

    /**
     * @param  list<array<string, mixed>>  $networks
     * @param  array<int, true>  $interfaceIds
     * @return list<array<string, mixed>>
     */
    private function withBroadcasters(array $networks, array $interfaceIds): array
    {
        $broadcasters = DB::table('wifi_network_broadcasters')
            ->whereIn('wifi_network_id', array_column($networks, 'id'))
            ->orderBy('id')
            ->get()
            ->groupBy('wifi_network_id');

        foreach ($networks as &$network) {
            $network['broadcaster_interface_ids'] = ($broadcasters[$network['id']] ?? collect())
                ->pluck('interface_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => isset($interfaceIds[$id]))
                ->values()
                ->all();
        }

        return $networks;
    }

    /**
     * @param  array<string, array<int, true>>  $exportedIds
     * @return list<array{tag_id: int, type: string, id: int}>
     */
    private function taggables(array $exportedIds): array
    {
        $out = [];
        foreach (TenantArchiveSchema::TAGGABLE_TYPES as $section => $class) {
            $rows = DB::table('taggables')
                ->where('taggable_type', $class)
                ->whereIn('tag_id', array_keys($exportedIds['tags']))
                ->orderBy('tag_id')
                ->orderBy('taggable_id')
                ->get();
            foreach ($rows as $row) {
                if (isset($exportedIds[$section][(int) $row->taggable_id])) {
                    $out[] = ['tag_id' => (int) $row->tag_id, 'type' => $section, 'id' => (int) $row->taggable_id];
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $files
     */
    private function writeZip(array $data, array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'trama-export-');
        $zip = new ZipArchive;
        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(__('tenants.archive_err_create'));
        }

        $zip->addFromString('data.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $disk = Storage::disk('public');
        foreach ($files as $file) {
            $zip->addFromString('files/'.$file, (string) $disk->get($file));
        }
        $zip->close();

        return $path;
    }
}
