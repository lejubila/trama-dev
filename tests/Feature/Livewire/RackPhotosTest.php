<?php

declare(strict_types=1);

use App\Livewire\Racks\Photos;
use App\Models\Rack;
use App\Models\RackPhoto;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

afterEach(function (): void {
    TenantContext::clear();
});

function bootRackPhotosScene(string $role): array
{
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create();
    $user->forceFill(['role' => $role])->save();
    $user->tenants()->attach($tenant);
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();
    actingAsInTenant($user, $tenant);

    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey()]);

    return [$tenant, $user, $rack];
}

it('uploads multiple photos as admin and stores files under rack scope', function (): void {
    Storage::fake('public');
    [$tenant, $user, $rack] = bootRackPhotosScene('admin');

    Livewire::test(Photos::class, ['rack' => $rack])
        ->set('newPhotos', [
            UploadedFile::fake()->image('a.jpg', 200, 200),
            UploadedFile::fake()->image('b.png', 300, 300),
        ])
        ->call('savePhotos')
        ->assertHasNoErrors();

    $photos = RackPhoto::query()->where('rack_id', $rack->getKey())->get();
    expect($photos)->toHaveCount(2);
    foreach ($photos as $p) {
        expect($p->photo_path)->toStartWith("rack-photos/{$tenant->getKey()}/{$rack->getKey()}/");
        expect($p->tenant_id)->toBe($tenant->getKey());
        expect($p->created_by)->toBe($user->getKey());
        Storage::disk('public')->assertExists($p->photo_path);
    }
});

it('deletes a photo and removes the file from disk', function (): void {
    Storage::fake('public');
    [$tenant, $user, $rack] = bootRackPhotosScene('admin');

    $path = 'rack-photos/test/x.jpg';
    Storage::disk('public')->put($path, 'dummy');
    $photo = RackPhoto::factory()->create([
        'rack_id' => $rack->getKey(),
        'photo_path' => $path,
    ]);

    Livewire::test(Photos::class, ['rack' => $rack])
        ->call('deletePhoto', $photo->getKey())
        ->assertHasNoErrors();

    expect(RackPhoto::query()->whereKey($photo->getKey())->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($path);
});

it('forbids cliente from uploading photos', function (): void {
    Storage::fake('public');
    [$tenant, $user, $rack] = bootRackPhotosScene('cliente');

    Livewire::test(Photos::class, ['rack' => $rack])
        ->set('newPhotos', [UploadedFile::fake()->image('a.jpg')])
        ->call('savePhotos')
        ->assertForbidden();

    expect(RackPhoto::query()->where('rack_id', $rack->getKey())->count())->toBe(0);
});

it('lets cliente view photos in the gallery', function (): void {
    [$tenant, $user, $rack] = bootRackPhotosScene('cliente');
    RackPhoto::factory()->create([
        'rack_id' => $rack->getKey(),
        'caption' => 'Vista frontale',
    ]);

    Livewire::test(Photos::class, ['rack' => $rack])
        ->assertOk()
        ->assertSee('Vista frontale');
});

it('isolates rack photos between tenants', function (): void {
    [$tenantA, $userA, $rackA] = bootRackPhotosScene('admin');
    RackPhoto::factory()->create([
        'rack_id' => $rackA->getKey(),
        'caption' => 'Foto-A-Only',
    ]);

    // Switch to a second tenant.
    TenantContext::clear();
    $tenantB = Tenant::factory()->create();
    TenantContext::setId($tenantB->getKey());
    $userB = User::factory()->create();
    $userB->forceFill(['role' => 'admin'])->save();
    $userB->tenants()->attach($tenantB);
    actingAsInTenant($userB, $tenantB);

    // Rack of tenant A is hidden from tenant B via BelongsToTenant scope.
    $rackBView = Rack::query()->find($rackA->getKey());
    expect($rackBView)->toBeNull();

    // Even constructing the component with the cross-tenant rack would have
    // failed at route-model binding in real traffic; the photo never leaks.
    expect(RackPhoto::query()->where('caption', 'Foto-A-Only')->exists())->toBeFalse();
});

it('updates the caption when an admin saves it', function (): void {
    [$tenant, $user, $rack] = bootRackPhotosScene('admin');
    $photo = RackPhoto::factory()->create([
        'rack_id' => $rack->getKey(),
        'caption' => null,
    ]);

    Livewire::test(Photos::class, ['rack' => $rack])
        ->call('startEditCaption', $photo->getKey())
        ->set('captionDraft', 'Cablaggio U12')
        ->call('saveCaption')
        ->assertHasNoErrors()
        ->assertSet('editingId', null);

    expect($photo->fresh()->caption)->toBe('Cablaggio U12');
});

it('navigates prev/next in the lightbox with wrap-around', function (): void {
    [$tenant, $user, $rack] = bootRackPhotosScene('admin');
    RackPhoto::factory()->count(3)->create(['rack_id' => $rack->getKey()]);

    Livewire::test(Photos::class, ['rack' => $rack])
        ->call('openLightbox', 0)
        ->assertSet('lightboxIndex', 0)
        ->call('next')->assertSet('lightboxIndex', 1)
        ->call('next')->assertSet('lightboxIndex', 2)
        ->call('next')->assertSet('lightboxIndex', 0)   // wrap forward
        ->call('prev')->assertSet('lightboxIndex', 2)   // wrap backward
        ->call('closeLightbox')->assertSet('lightboxIndex', -1);
});

it('rejects non-image files in the upload form', function (): void {
    Storage::fake('public');
    [$tenant, $user, $rack] = bootRackPhotosScene('admin');

    Livewire::test(Photos::class, ['rack' => $rack])
        ->set('newPhotos', [UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')])
        ->call('savePhotos')
        ->assertHasErrors(['newPhotos.0']);

    expect(RackPhoto::query()->where('rack_id', $rack->getKey())->count())->toBe(0);
});
