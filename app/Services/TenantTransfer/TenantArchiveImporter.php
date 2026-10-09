<?php

declare(strict_types=1);

namespace App\Services\TenantTransfer;

use App\Actions\Tenancy\CreateTenant;
use App\Models\Import;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Recreates an exported tenant as a brand-new tenant. Everything happens in
 * one transaction: either the whole client lands, or nothing does (files
 * already copied are removed on failure).
 *
 * Rows are written with the query builder on purpose: it skips observers
 * and auditing, which would otherwise fire thousands of times and, for
 * keystone pairs, try to "sync" half-imported siblings.
 */
class TenantArchiveImporter
{
    /** @var array<string, array<int, int>> section => [old id => new id] */
    private array $maps = [];

    /** @var list<string> files written so far, for cleanup on failure */
    private array $writtenFiles = [];

    /** @var array<string, list<string>> */
    private array $columns = [];

    public function __construct(private readonly CreateTenant $createTenant) {}

    /**
     * @param  array{name: string, slug: string|null, domain: string|null}  $attributes
     */
    public function import(TenantArchive $archive, User $by, array $attributes): Tenant
    {
        $this->maps = [];
        $this->writtenFiles = [];
        $previousTenant = TenantContext::id();

        try {
            return DB::transaction(function () use ($archive, $by, $attributes): Tenant {
                $tenant = $this->createTenant->execute($by, $attributes);
                $tenant->forceFill(['settings' => $archive->tenant()['settings']])->save();
                TenantContext::setId((int) $tenant->getKey());

                $this->importSections($archive, $tenant, $by);
                $this->importLinkGroupMembers($archive);
                $this->importBroadcasters($archive);
                $this->importTaggables($archive);
                $this->remapJsonIds();

                Import::create([
                    'tenant_id' => $tenant->getKey(),
                    'user_id' => $by->getKey(),
                    'type' => 'tenant_archive',
                    'status' => 'completed',
                    'summary' => [
                        'created' => array_sum(array_map('count', $this->maps)),
                        'errors' => [],
                        'source' => [
                            'name' => $archive->tenant()['name'],
                            'slug' => $archive->tenant()['slug'],
                            'exported_at' => $archive->exportedAt(),
                        ],
                        'counts' => array_map('count', $this->maps),
                    ],
                ]);

                return $tenant;
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($this->writtenFiles);
            TenantContext::setId($previousTenant);

            throw $e;
        }
    }

    private function importSections(TenantArchive $archive, Tenant $tenant, User $by): void
    {
        $oldTenantId = $archive->tenant()['id'];

        foreach (TenantArchiveSchema::sections() as $section => $def) {
            $this->maps[$section] = [];
            $table = $def['table'];
            $columns = $this->columns($table);
            $deferredValues = [];

            foreach ($archive->rows($section) as $row) {
                $oldId = $row['id'];
                $insert = array_intersect_key($row, array_flip($columns));
                foreach (TenantArchiveSchema::SKIPPED_COLUMNS as $col) {
                    unset($insert[$col]);
                }

                foreach ($def['refs'] ?? [] as $col => [$target, $required]) {
                    $insert[$col] = $this->mapRef($row[$col] ?? null, $target, $required, "{$section}.{$col}");
                }
                foreach (array_keys($def['deferred'] ?? []) as $col) {
                    $deferredValues[$oldId][$col] = $row[$col] ?? null;
                    $insert[$col] = null;
                }
                foreach ($def['json'] ?? [] as $col) {
                    if (array_key_exists($col, $insert) && $insert[$col] !== null) {
                        $insert[$col] = json_encode($insert[$col], JSON_THROW_ON_ERROR);
                    }
                }
                foreach ($def['files'] ?? [] as $col) {
                    if (array_key_exists($col, $insert)) {
                        $insert[$col] = $this->copyFile($archive, $insert[$col], $oldTenantId, (int) $tenant->getKey(), $insert);
                    }
                }
                if (($def['created_by'] ?? false) && in_array('created_by', $columns, true)) {
                    $insert['created_by'] = $by->getKey();
                }
                $insert['tenant_id'] = $tenant->getKey();

                $this->maps[$section][$oldId] = (int) DB::table($table)->insertGetId($insert);
            }

            // Second pass: self-references, now that the whole section has new ids.
            foreach ($deferredValues as $oldId => $values) {
                $update = [];
                foreach ($values as $col => $value) {
                    $update[$col] = $this->mapRef($value, $def['deferred'][$col], false, "{$section}.{$col}");
                }
                if (array_filter($update, fn ($v) => $v !== null) !== []) {
                    DB::table($table)->where('id', $this->maps[$section][$oldId])->update($update);
                }
            }
        }
    }

    /**
     * A dangling required reference means the archive is inconsistent (or
     * was edited by hand): abort rather than import a half-broken client.
     */
    private function mapRef(mixed $old, string $section, bool $required, string $where): ?int
    {
        if ($old === null) {
            if ($required) {
                throw new InvalidTenantArchiveException(__('tenants.archive_err_ref_missing', ['where' => $where]));
            }

            return null;
        }

        $new = is_int($old) || is_numeric($old) ? ($this->maps[$section][(int) $old] ?? null) : null;
        if ($new === null && $required) {
            throw new InvalidTenantArchiveException(__('tenants.archive_err_ref_invalid', ['where' => $where, 'value' => json_encode($old)]));
        }

        return $new;
    }

    private function importLinkGroupMembers(TenantArchive $archive): void
    {
        foreach ($archive->rows('link_groups') as $group) {
            foreach ($group['connection_ids'] ?? [] as $connectionId) {
                DB::table('link_group_connection')->insert([
                    'link_group_id' => $this->maps['link_groups'][$group['id']],
                    'connection_id' => $this->mapRef($connectionId, 'connections', true, 'link_groups.connection_ids'),
                ]);
            }
        }
    }

    private function importBroadcasters(TenantArchive $archive): void
    {
        $now = now();
        foreach ($archive->rows('wifi_networks') as $network) {
            foreach ($network['broadcaster_interface_ids'] ?? [] as $interfaceId) {
                DB::table('wifi_network_broadcasters')->insert([
                    'wifi_network_id' => $this->maps['wifi_networks'][$network['id']],
                    'interface_id' => $this->mapRef($interfaceId, 'interfaces', true, 'wifi_networks.broadcaster_interface_ids'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function importTaggables(TenantArchive $archive): void
    {
        foreach ($archive->data['taggables'] ?? [] as $t) {
            DB::table('taggables')->insert([
                'tag_id' => $this->mapRef($t['tag_id'], 'tags', true, 'taggables.tag_id'),
                'taggable_type' => TenantArchiveSchema::TAGGABLE_TYPES[$t['type']],
                'taggable_id' => $this->mapRef($t['id'], $t['type'], true, 'taggables.id'),
            ]);
        }
    }

    private function remapJsonIds(): void
    {
        $remapper = new ViewStateRemapper($this->maps);

        foreach (['topology_snapshots' => ['view_state', 'viewState'], 'documents' => ['parameters', 'documentParameters']] as $section => [$col, $method]) {
            foreach ($this->maps[$section] as $newId) {
                $raw = DB::table($section)->where('id', $newId)->value($col);
                $decoded = is_string($raw) ? json_decode($raw, true) : null;
                if (is_array($decoded)) {
                    DB::table($section)->where('id', $newId)->update([
                        $col => json_encode($remapper->{$method}($decoded), JSON_THROW_ON_ERROR),
                    ]);
                }
            }
        }
    }

    /**
     * Files follow the app's "<kind>/<tenant id>/…" layout; the tenant segment
     * (and the rack segment for rack photos) is rewritten to the new ids so
     * the imported client never shares files with the original.
     *
     * @param  array<string, mixed>  $row  row being inserted (refs already remapped)
     */
    private function copyFile(TenantArchive $archive, mixed $path, ?int $oldTenantId, int $newTenantId, array $row): ?string
    {
        if (! is_string($path) || ! $archive->hasFile($path)) {
            return null;
        }
        $contents = $archive->fileContents($path);
        if ($contents === null) {
            return null;
        }

        $segments = explode('/', $path);
        if (count($segments) >= 3 && $oldTenantId !== null && $segments[1] === (string) $oldTenantId) {
            $segments[1] = (string) $newTenantId;
            if ($segments[0] === 'rack-photos' && count($segments) >= 4 && isset($row['rack_id'])) {
                $segments[2] = (string) $row['rack_id'];
            }
            $target = implode('/', $segments);
        } else {
            $target = "imported/{$newTenantId}/".basename($path);
        }

        $disk = Storage::disk('public');
        if ($disk->exists($target)) {
            $target = dirname($target).'/'.Str::random(8).'-'.basename($target);
        }
        $disk->put($target, $contents);
        $this->writtenFiles[] = $target;

        return $target;
    }

    /**
     * @return list<string>
     */
    private function columns(string $table): array
    {
        return $this->columns[$table] ??= Schema::getColumnListing($table);
    }
}
