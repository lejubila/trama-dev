<?php

declare(strict_types=1);

use App\Livewire\Topology\Graph;
use App\Livewire\Topology\SnapshotIndex;
use App\Livewire\Topology\SnapshotSaveModal;
use App\Livewire\Topology\SnapshotShow;
use App\Models\Tenant;
use App\Models\TopologySnapshot;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// Minimal valid 1x1 transparent PNG, base64-encoded with the data: prefix.
const TEST_PNG_DATA_URL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

afterEach(function (): void {
    TenantContext::clear();
});

function bootSnapshotScene(string $role): array
{
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create();
    $user->forceFill(['role' => $role])->save();
    $user->tenants()->attach($tenant);
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();
    actingAsInTenant($user, $tenant);

    return [$tenant, $user];
}

it('saves a snapshot, creating row and file', function (): void {
    Storage::fake('public');
    [$tenant, $user] = bootSnapshotScene('admin');

    Livewire::test(SnapshotSaveModal::class)
        ->call('openModal', ['siteId' => 0, 'layout' => 'dagre', 'filterTypes' => ['switch']])
        ->set('title', 'Pre-upgrade')
        ->set('description', 'Stato di partenza')
        ->set('snapshotDate', '2026-05-17')
        ->set('snapshotImageBase64', TEST_PNG_DATA_URL)
        ->call('save')
        ->assertHasNoErrors();

    $snap = TopologySnapshot::query()->where('title', 'Pre-upgrade')->first();
    expect($snap)->not->toBeNull()
        ->and($snap->tenant_id)->toBe($tenant->getKey())
        ->and($snap->created_by)->toBe($user->getKey())
        ->and($snap->view_state['layout'] ?? null)->toBe('dagre');

    Storage::disk('public')->assertExists($snap->image_path);
});

it('isolates snapshots between tenants', function (): void {
    [$tenantA, $userA] = bootSnapshotScene('admin');
    TopologySnapshot::factory()->create(['title' => 'Snap-A']);

    TenantContext::clear();
    $tenantB = Tenant::factory()->create();
    TenantContext::setId($tenantB->getKey());
    TopologySnapshot::factory()->create(['title' => 'Snap-B']);

    /** @var User $userB */
    $userB = User::factory()->create();
    $userB->forceFill(['role' => 'admin'])->save();
    $userB->tenants()->attach($tenantB);
    actingAsInTenant($userB, $tenantB);

    Livewire::test(SnapshotIndex::class)->assertSee('Snap-B')->assertDontSee('Snap-A');
});

it('forbids cliente from deleting a snapshot', function (): void {
    [$tenant, $user] = bootSnapshotScene('cliente');
    $snap = TopologySnapshot::factory()->create();

    Livewire::test(SnapshotIndex::class)
        ->call('delete', $snap->getKey())
        ->assertForbidden();

    expect(TopologySnapshot::query()->whereKey($snap->getKey())->exists())->toBeTrue();
});

it('allows admin to delete a snapshot and removes the file', function (): void {
    Storage::fake('public');
    [$tenant, $user] = bootSnapshotScene('admin');

    // Create a real-ish file so the disk delete branch executes.
    Storage::disk('public')->put('topology-snapshots/test/x.png', 'dummy');
    $snap = TopologySnapshot::factory()->create(['image_path' => 'topology-snapshots/test/x.png']);

    Livewire::test(SnapshotIndex::class)
        ->call('delete', $snap->getKey())
        ->assertHasNoErrors();

    expect(TopologySnapshot::query()->whereKey($snap->getKey())->exists())->toBeFalse();
    Storage::disk('public')->assertMissing('topology-snapshots/test/x.png');
});

it('computes prev/next neighbors ordered by date desc, id desc', function (): void {
    [$tenant, $user] = bootSnapshotScene('admin');

    $older = TopologySnapshot::factory()->create(['snapshot_date' => '2026-05-10']);
    $mid = TopologySnapshot::factory()->create(['snapshot_date' => '2026-05-12']);
    $newer = TopologySnapshot::factory()->create(['snapshot_date' => '2026-05-15']);

    $component = Livewire::test(SnapshotShow::class, ['snapshot' => $mid])->assertOk();
    $rendered = $component->html();

    // The neighbor links contain the snapshot routes; assert both ids appear.
    expect($rendered)->toContain((string) $older->getKey())
        ->and($rendered)->toContain((string) $newer->getKey());
});

it('builds a live-topology URL from view_state on openLive', function (): void {
    [$tenant, $user] = bootSnapshotScene('admin');

    $snap = TopologySnapshot::factory()->create([
        'view_state' => [
            'siteId' => 3,
            'statusFilter' => '',
            'vlanFilter' => 0,
            'layout' => 'dagre',
            'filterTypes' => ['switch'],
            'tagFilters' => [7, 9],
            'groupByRack' => true,
            'groupByRoom' => true,
            'groupBySite' => true,
        ],
    ]);

    Livewire::test(SnapshotShow::class, ['snapshot' => $snap])
        ->call('openLive')
        ->assertRedirect(route('topology.index', [
            'siteId' => 3, 'layout' => 'dagre', 'filterTypes' => ['switch'], 'tagFilters' => [7, 9],
            'groupByRack' => true, 'groupBySite' => true, 'groupByRoom' => true,
        ]));
});

it('includes snapshotPreset in openLive URL when view_state has nodePositions', function (): void {
    [$tenant, $user] = bootSnapshotScene('admin');

    $snap = TopologySnapshot::factory()->create([
        'view_state' => [
            'layout' => 'dagre',
            'nodePositions' => ['eq-1' => [100, 50], 'eq-2' => [200, 150]],
            'zoom' => 0.9,
            'pan' => [10, 20],
        ],
    ]);

    Livewire::test(SnapshotShow::class, ['snapshot' => $snap])
        ->call('openLive')
        ->assertRedirect(route('topology.index', [
            'layout' => 'dagre',
            'snapshotPreset' => $snap->getKey(),
        ]));
});

it('exposes restore data to the Graph view when snapshotPreset matches', function (): void {
    [$tenant, $user] = bootSnapshotScene('admin');

    $snap = TopologySnapshot::factory()->create([
        'view_state' => [
            'nodePositions' => ['eq-7' => [123, 456]],
            'zoom' => 1.25,
            'pan' => [5, 6],
        ],
    ]);

    $component = Livewire::test(Graph::class, [
        'snapshotPreset' => $snap->getKey(),
    ])->assertOk();

    // The view passes $restore into the topologyGraph() Alpine factory via
    // @js(...) inside an HTML attribute, so double-quotes are entity-escaped.
    // Look for the unescaped substrings that survive both encodings.
    $html = $component->html();
    expect($html)->toContain('nodePositions')
        ->and($html)->toContain('eq-7')
        ->and($html)->toContain('123')
        ->and($html)->toContain('456')
        ->and($html)->toContain('1.25');
});

it('ignores cross-tenant snapshotPreset (no restore leak)', function (): void {
    [$tenantA, $userA] = bootSnapshotScene('admin');
    $snapA = TopologySnapshot::factory()->create([
        'view_state' => ['nodePositions' => ['eq-1' => [10, 20]]],
    ]);

    // Switch to a different tenant.
    TenantContext::clear();
    $tenantB = Tenant::factory()->create();
    TenantContext::setId($tenantB->getKey());
    $userB = User::factory()->create();
    $userB->forceFill(['role' => 'admin'])->save();
    $userB->tenants()->attach($tenantB);
    actingAsInTenant($userB, $tenantB);

    $component = Livewire::test(Graph::class, [
        'snapshotPreset' => $snapA->getKey(),
    ])->assertOk();

    // Cross-tenant snapshot is filtered out by BelongsToTenant: restore stays
    // null and the saved node id never reaches the HTML.
    expect($component->html())->not->toContain('eq-1');
});

it('overwrites an existing snapshot, replacing image and metadata but not author', function (): void {
    Storage::fake('public');
    [$tenant, $user] = bootSnapshotScene('admin');

    // Seed an existing snapshot with a real file on disk.
    /** @var User $author */
    $author = User::factory()->create();
    $existing = TopologySnapshot::factory()->create([
        'title' => 'Old title',
        'description' => 'Old desc',
        'snapshot_date' => '2026-05-01',
        'image_path' => "topology-snapshots/{$tenant->getKey()}/old.png",
        'created_by' => $author->getKey(),
    ]);
    Storage::disk('public')->put($existing->image_path, 'OLDBYTES');

    Livewire::test(SnapshotSaveModal::class)
        ->call('openModal', ['layout' => 'cose-bilkent'])
        ->set('mode', 'overwrite')
        ->set('overwriteId', $existing->getKey())
        ->set('title', 'Refreshed title')
        ->set('description', 'Refreshed desc')
        ->set('snapshotDate', '2026-06-09')
        ->set('snapshotImageBase64', TEST_PNG_DATA_URL)
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $existing->fresh();
    expect($fresh->title)->toBe('Refreshed title')
        ->and($fresh->description)->toBe('Refreshed desc')
        ->and($fresh->snapshot_date->toDateString())->toBe('2026-06-09')
        ->and($fresh->image_path)->not->toBe($existing->image_path)
        ->and($fresh->view_state['layout'] ?? null)->toBe('cose-bilkent')
        ->and($fresh->created_by)->toBe($author->getKey());

    Storage::disk('public')->assertExists($fresh->image_path);
    Storage::disk('public')->assertMissing($existing->image_path);
});

it('pre-fills metadata when picking a snapshot to overwrite', function (): void {
    Storage::fake('public');
    [$tenant, $user] = bootSnapshotScene('admin');

    $existing = TopologySnapshot::factory()->create([
        'title' => 'Existing',
        'description' => 'Prev desc',
        'snapshot_date' => '2026-04-15',
    ]);

    Livewire::test(SnapshotSaveModal::class)
        ->call('openModal', [])
        ->set('mode', 'overwrite')
        ->set('overwriteId', $existing->getKey())
        ->assertSet('title', 'Existing')
        ->assertSet('description', 'Prev desc')
        ->assertSet('snapshotDate', '2026-04-15');
});

it('forbids cliente from overwriting a snapshot', function (): void {
    Storage::fake('public');
    [$tenant, $user] = bootSnapshotScene('cliente');

    $existing = TopologySnapshot::factory()->create([
        'image_path' => "topology-snapshots/{$tenant->getKey()}/old.png",
    ]);
    Storage::disk('public')->put($existing->image_path, 'OLDBYTES');

    Livewire::test(SnapshotSaveModal::class)
        ->call('openModal', [])
        ->set('mode', 'overwrite')
        ->set('overwriteId', $existing->getKey())
        ->set('title', 'x')
        ->set('snapshotDate', '2026-06-09')
        ->set('snapshotImageBase64', TEST_PNG_DATA_URL)
        ->call('save')
        ->assertForbidden();

    // The existing image must still be there because the overwrite was rejected.
    Storage::disk('public')->assertExists($existing->image_path);
});

it('edits snapshot metadata from the list without touching image or view_state', function (): void {
    [$tenant, $user] = bootSnapshotScene('admin');

    $existing = TopologySnapshot::factory()->create([
        'title' => 'Old',
        'description' => 'd',
        'snapshot_date' => '2026-05-01',
        'image_path' => 'topology-snapshots/keep.png',
        'view_state' => ['layout' => 'dagre'],
    ]);

    Livewire::test(SnapshotIndex::class)
        ->call('openEdit', $existing->getKey())
        ->assertSet('editTitle', 'Old')
        ->set('editTitle', 'New title')
        ->set('editDescription', 'New desc')
        ->set('editSnapshotDate', '2026-06-01')
        ->call('saveEdit')
        ->assertHasNoErrors()
        ->assertSet('showEditForm', false);

    $fresh = $existing->fresh();
    expect($fresh->title)->toBe('New title')
        ->and($fresh->description)->toBe('New desc')
        ->and($fresh->snapshot_date->toDateString())->toBe('2026-06-01')
        ->and($fresh->image_path)->toBe('topology-snapshots/keep.png')
        ->and($fresh->view_state)->toBe(['layout' => 'dagre']);
});

it('forbids cliente from editing snapshot metadata', function (): void {
    [$tenant, $user] = bootSnapshotScene('cliente');
    $snap = TopologySnapshot::factory()->create();

    Livewire::test(SnapshotIndex::class)
        ->call('openEdit', $snap->getKey())
        ->assertForbidden();
});
