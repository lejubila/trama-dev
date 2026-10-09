<?php

declare(strict_types=1);

namespace App\Services\TenantTransfer;

use ZipArchive;

/**
 * Opens and validates an uploaded tenant archive. Only the structure is
 * checked here (format, ids, file paths); referential integrity is enforced
 * by the importer, which aborts on any dangling required reference.
 */
class TenantArchiveReader
{
    private const MAX_JSON_BYTES = 100 * 1024 * 1024;

    private const MAX_FILE_BYTES = 25 * 1024 * 1024;

    private const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'pdf'];

    public function read(string $zipPath): TenantArchive
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new InvalidTenantArchiveException(__('tenants.archive_err_not_zip'));
        }

        try {
            $data = $this->readData($zip);
            $this->validateSections($data);
            $files = $this->vetFiles($zip, $data);
        } catch (InvalidTenantArchiveException $e) {
            $zip->close();
            throw $e;
        }

        return new TenantArchive($zip, $data, $files);
    }

    /**
     * @return array<string, mixed>
     */
    private function readData(ZipArchive $zip): array
    {
        $stat = $zip->statName('data.json');
        if ($stat === false) {
            throw new InvalidTenantArchiveException(__('tenants.archive_err_no_data'));
        }
        if ($stat['size'] > self::MAX_JSON_BYTES) {
            throw new InvalidTenantArchiveException(__('tenants.archive_err_data_too_big'));
        }

        $data = json_decode((string) $zip->getFromName('data.json'), true);
        if (! is_array($data) || ($data['format'] ?? null) !== TenantArchiveSchema::FORMAT) {
            throw new InvalidTenantArchiveException(__('tenants.archive_err_format'));
        }
        if (($data['version'] ?? null) !== TenantArchiveSchema::VERSION) {
            throw new InvalidTenantArchiveException(__('tenants.archive_err_version', ['version' => json_encode($data['version'] ?? null)]));
        }
        if (! is_array($data['tenant'] ?? null) || ! is_string($data['tenant']['name'] ?? null) || trim($data['tenant']['name']) === '') {
            throw new InvalidTenantArchiveException(__('tenants.archive_err_tenant'));
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateSections(array $data): void
    {
        foreach (array_keys(TenantArchiveSchema::sections()) as $section) {
            $rows = $data[$section] ?? [];
            if (! is_array($rows) || ! array_is_list($rows)) {
                throw new InvalidTenantArchiveException(__('tenants.archive_err_section', ['section' => $section]));
            }
            $seen = [];
            foreach ($rows as $i => $row) {
                if (! is_array($row) || ! is_int($row['id'] ?? null)) {
                    throw new InvalidTenantArchiveException(__('tenants.archive_err_row_id', ['row' => $i, 'section' => $section]));
                }
                if (isset($seen[$row['id']])) {
                    throw new InvalidTenantArchiveException(__('tenants.archive_err_dup_id', ['id' => $row['id'], 'section' => $section]));
                }
                $seen[$row['id']] = true;
            }
        }

        foreach (['link_groups' => 'connection_ids', 'wifi_networks' => 'broadcaster_interface_ids'] as $section => $key) {
            foreach ($data[$section] ?? [] as $row) {
                $ids = $row[$key] ?? [];
                if (! is_array($ids) || array_filter($ids, fn ($id) => ! is_int($id)) !== []) {
                    throw new InvalidTenantArchiveException(__('tenants.archive_err_field', ['field' => $key, 'section' => $section]));
                }
            }
        }

        $taggables = $data['taggables'] ?? [];
        if (! is_array($taggables)) {
            throw new InvalidTenantArchiveException(__('tenants.archive_err_section', ['section' => 'taggables']));
        }
        foreach ($taggables as $t) {
            if (! is_array($t) || ! is_int($t['tag_id'] ?? null) || ! is_int($t['id'] ?? null)
                || ! array_key_exists((string) ($t['type'] ?? ''), TenantArchiveSchema::TAGGABLE_TYPES)) {
                throw new InvalidTenantArchiveException(__('tenants.archive_err_section', ['section' => 'taggables']));
            }
        }
    }

    /**
     * Collects the file paths referenced by the data and keeps those actually
     * present in the zip. Paths are re-checked here because they end up in
     * storage: no traversal, no absolute paths, only known media types.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, true>
     */
    private function vetFiles(ZipArchive $zip, array $data): array
    {
        $files = [];
        foreach (TenantArchiveSchema::sections() as $section => $def) {
            foreach ($def['files'] ?? [] as $col) {
                foreach ($data[$section] ?? [] as $row) {
                    $path = $row[$col] ?? null;
                    if ($path === null) {
                        continue;
                    }
                    if (! is_string($path) || ! $this->isSafePath($path)) {
                        throw new InvalidTenantArchiveException(__('tenants.archive_err_path', ['path' => json_encode($path)]));
                    }
                    $stat = $zip->statName('files/'.$path);
                    if ($stat === false) {
                        continue;
                    }
                    if ($stat['size'] > self::MAX_FILE_BYTES) {
                        throw new InvalidTenantArchiveException(__('tenants.archive_err_file_size', ['path' => $path]));
                    }
                    $files[$path] = true;
                }
            }
        }

        return $files;
    }

    private function isSafePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\')) {
            return false;
        }
        if (preg_match('#^[A-Za-z0-9._\-/]+$#', $path) !== 1) {
            return false;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::ALLOWED_EXTENSIONS, true);
    }
}
