<?php

declare(strict_types=1);

namespace App\Services\TenantTransfer;

use ZipArchive;

/**
 * A validated, opened tenant archive. File access is restricted to entries
 * the reader has already vetted, so callers can't be tricked into reading
 * arbitrary zip members.
 */
final class TenantArchive
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, true>  $files  vetted original paths present in the zip
     */
    public function __construct(
        private readonly ZipArchive $zip,
        public readonly array $data,
        private readonly array $files,
    ) {}

    /**
     * @return array{name: string, slug: string, domain: string|null, settings: array<string, mixed>, id: int|null}
     */
    public function tenant(): array
    {
        $t = $this->data['tenant'];

        return [
            'id' => isset($t['id']) ? (int) $t['id'] : null,
            'name' => (string) $t['name'],
            'slug' => (string) ($t['slug'] ?? ''),
            'domain' => isset($t['domain']) && $t['domain'] !== '' ? (string) $t['domain'] : null,
            'settings' => is_array($t['settings'] ?? null) ? $t['settings'] : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(string $section): array
    {
        return $this->data[$section] ?? [];
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];
        foreach (array_keys(TenantArchiveSchema::labels()) as $section) {
            $counts[$section] = count($this->rows($section));
        }
        $counts['files'] = count($this->files);

        return $counts;
    }

    public function exportedAt(): ?string
    {
        return isset($this->data['exported_at']) ? (string) $this->data['exported_at'] : null;
    }

    public function hasFile(string $path): bool
    {
        return isset($this->files[$path]);
    }

    public function fileContents(string $path): ?string
    {
        if (! $this->hasFile($path)) {
            return null;
        }
        $bytes = $this->zip->getFromName('files/'.$path);

        return $bytes === false ? null : $bytes;
    }

    public function close(): void
    {
        @$this->zip->close();
    }
}
