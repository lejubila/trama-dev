<?php

declare(strict_types=1);

use App\Models\DeviceIcon;
use App\Services\Icons\DefaultIcons;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

it('ships a bundled icon for every kind', function (): void {
    expect(array_keys((new DefaultIcons)->bundled()))->toEqualCanonicalizing(DefaultIcons::kinds());
});

it('installs the bundled set as global icons', function (): void {
    $this->artisan('icons:defaults')->assertSuccessful();

    $icons = DeviceIcon::query()->whereNull('tenant_id')->pluck('image_path', 'kind');
    expect($icons->keys()->all())->toEqualCanonicalizing(DefaultIcons::kinds())
        ->and(Storage::disk('public')->get($icons['switch']))->toBe(file_get_contents(database_path('icons/switch.svg')));

    // Running it again is a no-op.
    $this->artisan('icons:defaults')->assertSuccessful();
    expect(DeviceIcon::query()->whereNull('tenant_id')->count())->toBe(count(DefaultIcons::kinds()));
});

it('never replaces a global icon customised from the UI', function (): void {
    Storage::disk('public')->put('icons/global/custom.png', 'PNG');
    DeviceIcon::query()->create(['tenant_id' => null, 'kind' => 'switch', 'image_path' => 'icons/global/custom.png']);

    (new DefaultIcons)->install();

    expect(DeviceIcon::query()->whereNull('tenant_id')->where('kind', 'switch')->value('image_path'))->toBe('icons/global/custom.png');
});

it('exports the configured global icons one file per kind', function (): void {
    $dir = sys_get_temp_dir().'/trama-icons-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/router.svg', 'stale');
    Storage::disk('public')->put('icons/global/abc.svg', '<svg/>');
    Storage::disk('public')->put('icons/global/def.png', 'PNG');
    DeviceIcon::query()->create(['tenant_id' => null, 'kind' => 'switch', 'image_path' => 'icons/global/abc.svg']);
    DeviceIcon::query()->create(['tenant_id' => null, 'kind' => 'rack', 'image_path' => 'icons/global/def.png']);

    $this->artisan('icons:defaults', ['--export' => true, '--path' => $dir])->assertSuccessful();

    $files = array_map('basename', glob($dir.'/*'));
    sort($files);
    expect($files)->toBe(['rack.png', 'switch.svg'])
        ->and(file_get_contents($dir.'/switch.svg'))->toBe('<svg/>');

    array_map('unlink', glob($dir.'/*'));
    rmdir($dir);
});
