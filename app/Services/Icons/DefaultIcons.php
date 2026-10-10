<?php

declare(strict_types=1);

namespace App\Services\Icons;

use App\Enums\EquipmentType;
use App\Models\DeviceIcon;
use Illuminate\Support\Facades\Storage;

/**
 * The global icon set shipped with the project, as one file per kind in
 * database/icons (e.g. "switch.svg"). Global icons otherwise live only in the
 * database and on the public disk, so fresh installs and demo resets would
 * start without any.
 */
class DefaultIcons
{
    public function __construct(private readonly ?string $directory = null) {}

    public function directory(): string
    {
        return $this->directory ?? database_path('icons');
    }

    /**
     * @return list<string>
     */
    public static function kinds(): array
    {
        return ['rack', ...array_map(fn (EquipmentType $t) => $t->value, EquipmentType::cases())];
    }

    /**
     * Adds the bundled icon for every kind that has no global icon yet:
     * icons already customised from the UI are never replaced.
     *
     * @return int number of icons installed
     */
    public function install(): int
    {
        $existing = DeviceIcon::query()->whereNull('tenant_id')->pluck('kind')->all();
        $disk = Storage::disk('public');
        $installed = 0;

        foreach ($this->bundled() as $kind => $source) {
            if (in_array($kind, $existing, true)) {
                continue;
            }

            $path = 'icons/global/'.basename($source);
            $disk->put($path, (string) file_get_contents($source));
            DeviceIcon::query()->create(['tenant_id' => null, 'kind' => $kind, 'image_path' => $path]);
            $installed++;
        }

        return $installed;
    }

    /**
     * Replaces the bundled set with the global icons currently configured,
     * so the defaults can be curated from the UI and then committed.
     *
     * @return int number of icons exported
     */
    public function export(): int
    {
        $dir = $this->directory();
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        foreach ($this->bundled() as $file) {
            unlink($file);
        }

        $disk = Storage::disk('public');
        $exported = 0;
        $icons = DeviceIcon::query()->whereNull('tenant_id')->whereIn('kind', self::kinds())->orderBy('kind')->get();

        foreach ($icons as $icon) {
            if (! $disk->exists($icon->image_path)) {
                continue;
            }
            $extension = strtolower(pathinfo($icon->image_path, PATHINFO_EXTENSION)) ?: 'svg';
            file_put_contents("{$dir}/{$icon->kind}.{$extension}", (string) $disk->get($icon->image_path));
            $exported++;
        }

        return $exported;
    }

    /**
     * @return array<string, string> kind => absolute path of the bundled file
     */
    public function bundled(): array
    {
        $files = [];
        foreach (glob($this->directory().'/*.*') ?: [] as $file) {
            $kind = pathinfo($file, PATHINFO_FILENAME);
            if (in_array($kind, self::kinds(), true)) {
                $files[$kind] = $file;
            }
        }
        ksort($files);

        return $files;
    }
}
