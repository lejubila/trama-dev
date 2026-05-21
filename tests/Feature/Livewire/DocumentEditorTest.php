<?php

declare(strict_types=1);

use App\Livewire\Documents\Editor as DocumentEditor;
use App\Livewire\Documents\Index as DocumentIndex;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\TopologySnapshot;
use App\Models\User;
use App\Services\Export\DocumentPdfBuilder;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

afterEach(function (): void {
    TenantContext::clear();
});

function bootDocumentsScene(string $role): array
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

/**
 * Stub the PDF builder so tests don't spawn Chromium. We still want the
 * Document row + pdf_path to update though, so the stub mimics build()'s
 * persistence side-effect.
 */
function bindFakeDocumentPdfBuilder(): void
{
    app()->bind(DocumentPdfBuilder::class, function () {
        return new class extends DocumentPdfBuilder
        {
            protected function renderPdf(string $html, string $absolutePath): void
            {
                // Skip Chromium; write a placeholder so the file exists.
                @mkdir(dirname($absolutePath), 0777, true);
                file_put_contents($absolutePath, '%PDF-1.4 fake');
            }
        };
    });
}

it('creates a document and persists parameters with a generated PDF path', function (): void {
    Storage::fake('public');
    bindFakeDocumentPdfBuilder();
    [$tenant, $user] = bootDocumentsScene('admin');

    $site = Site::factory()->create();

    Livewire::test(DocumentEditor::class)
        ->set('title', 'Documentazione cliente X')
        ->set('description', 'Riepilogo iniziale.')
        ->set('documentDate', '2026-05-19')
        ->set('sitesEnabled', true)
        ->set('sitesDescription', 'Elenco delle sedi.')
        ->set('sitesIds', [$site->getKey()])
        ->call('save')
        ->assertHasNoErrors();

    $doc = Document::query()->where('title', 'Documentazione cliente X')->first();
    expect($doc)->not->toBeNull()
        ->and($doc->tenant_id)->toBe($tenant->getKey())
        ->and($doc->created_by)->toBe($user->getKey())
        ->and($doc->pdf_path)->not->toBeNull()
        ->and($doc->parameters['sections']['sites']['enabled'] ?? null)->toBeTrue()
        ->and($doc->parameters['sections']['sites']['ids'] ?? [])->toBe([$site->getKey()]);

    Storage::disk('public')->assertExists($doc->pdf_path);
});

it('updates parameters of an existing document and regenerates the PDF', function (): void {
    Storage::fake('public');
    bindFakeDocumentPdfBuilder();
    [$tenant, $user] = bootDocumentsScene('admin');

    $site = Site::factory()->create();
    $doc = Document::factory()->create([
        'title' => 'Old title',
        'document_date' => '2026-05-01',
    ]);

    Livewire::test(DocumentEditor::class, ['document' => $doc])
        ->set('title', 'Updated title')
        ->set('sitesEnabled', true)
        ->set('sitesIds', [$site->getKey()])
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $doc->fresh();
    expect($fresh->title)->toBe('Updated title')
        ->and($fresh->parameters['sections']['sites']['enabled'])->toBeTrue()
        ->and($fresh->parameters['sections']['sites']['ids'])->toBe([$site->getKey()])
        ->and($fresh->pdf_path)->not->toBeNull();
});

it('captures per-snapshot orientation correctly', function (): void {
    Storage::fake('public');
    bindFakeDocumentPdfBuilder();
    [$tenant, $user] = bootDocumentsScene('admin');

    $snapA = TopologySnapshot::factory()->create();
    $snapB = TopologySnapshot::factory()->create();

    Livewire::test(DocumentEditor::class)
        ->set('title', 'Doc with topology')
        ->set('documentDate', '2026-05-19')
        ->set('topologiesEnabled', true)
        ->call('toggleSnapshot', $snapA->getKey())
        ->call('toggleSnapshot', $snapB->getKey())
        ->call('setSnapshotOrientation', $snapA->getKey(), 'landscape')
        ->call('save')
        ->assertHasNoErrors();

    $doc = Document::query()->where('title', 'Doc with topology')->first();
    $items = $doc->parameters['sections']['topologies']['items'] ?? [];
    $byId = collect($items)->keyBy('id');
    expect($byId[$snapA->getKey()]['orientation'])->toBe('landscape')
        ->and($byId[$snapB->getKey()]['orientation'])->toBe('portrait');
});

it('forbids cliente from creating a document', function (): void {
    bindFakeDocumentPdfBuilder();
    [$tenant, $user] = bootDocumentsScene('cliente');

    Livewire::test(DocumentEditor::class)->assertForbidden();
});

it('lets cliente view the documents list', function (): void {
    [$tenant, $user] = bootDocumentsScene('cliente');
    Document::factory()->create(['title' => 'Visibile']);

    Livewire::test(DocumentIndex::class)
        ->assertOk()
        ->assertSee('Visibile');
});

it('isolates documents between tenants', function (): void {
    [$tenantA, $userA] = bootDocumentsScene('admin');
    Document::factory()->create(['title' => 'Doc-A']);

    TenantContext::clear();
    $tenantB = Tenant::factory()->create();
    TenantContext::setId($tenantB->getKey());
    Document::factory()->create(['title' => 'Doc-B']);

    /** @var User $userB */
    $userB = User::factory()->create();
    $userB->forceFill(['role' => 'admin'])->save();
    $userB->tenants()->attach($tenantB);
    actingAsInTenant($userB, $tenantB);

    Livewire::test(DocumentIndex::class)
        ->assertSee('Doc-B')
        ->assertDontSee('Doc-A');
});

it('admin can delete a document and removes the PDF file', function (): void {
    Storage::fake('public');
    [$tenant, $user] = bootDocumentsScene('admin');

    Storage::disk('public')->put('documents/test/doc-9.pdf', '%PDF-1.4 fake');
    $doc = Document::factory()->create(['pdf_path' => 'documents/test/doc-9.pdf']);

    Livewire::test(DocumentIndex::class)
        ->call('delete', $doc->getKey())
        ->assertHasNoErrors();

    expect(Document::query()->whereKey($doc->getKey())->exists())->toBeFalse();
    Storage::disk('public')->assertMissing('documents/test/doc-9.pdf');
});

it('reorders rooms within a site via moveRoom', function (): void {
    [$tenant, $user] = bootDocumentsScene('admin');

    $site = Site::factory()->create();
    $roomA = Room::factory()->create(['site_id' => $site->getKey(), 'name' => 'A']);
    $roomB = Room::factory()->create(['site_id' => $site->getKey(), 'name' => 'B']);

    Livewire::test(DocumentEditor::class)
        ->set('roomsIds', [$roomA->getKey(), $roomB->getKey()])
        ->call('moveRoom', $roomA->getKey(), 1)
        ->assertSet('roomsIds', [$roomB->getKey(), $roomA->getKey()])
        ->call('moveRoom', $roomA->getKey(), -1)
        ->assertSet('roomsIds', [$roomA->getKey(), $roomB->getKey()]);
});

it('reorders rooms even when ids arrive as strings (wire:model.live)', function (): void {
    [$tenant, $user] = bootDocumentsScene('admin');

    $site = Site::factory()->create();
    $roomA = Room::factory()->create(['site_id' => $site->getKey(), 'name' => 'A']);
    $roomB = Room::factory()->create(['site_id' => $site->getKey(), 'name' => 'B']);

    // The browser's checkboxes bind string values; the strict comparison in
    // move() must still reorder them.
    Livewire::test(DocumentEditor::class)
        ->set('roomsIds', [(string) $roomA->getKey(), (string) $roomB->getKey()])
        ->call('moveRoom', $roomA->getKey(), 1)
        ->assertSet('roomsIds', [$roomB->getKey(), $roomA->getKey()]);
});

it('auto-selects a racks devices (in rack order) when the rack is selected', function (): void {
    [$tenant, $user] = bootDocumentsScene('admin');

    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey()]);
    $top = Equipment::factory()->create(['rack_id' => $rack->getKey(), 'name' => 'Z', 'mounted' => true, 'position_u_start' => 40, 'position_u_height' => 1]);
    $bottom = Equipment::factory()->create(['rack_id' => $rack->getKey(), 'name' => 'A', 'mounted' => true, 'position_u_start' => 1, 'position_u_height' => 1]);

    $component = Livewire::test(DocumentEditor::class)
        ->set('racksIds', [$rack->getKey()]);

    // Highest U (top of rack) first.
    expect($component->get('equipmentIds'))->toBe([$top->getKey(), $bottom->getKey()])
        ->and($component->get('equipmentEnabled'))->toBeTrue();
});

it('reorders selected topologies via moveTopology', function (): void {
    [$tenant, $user] = bootDocumentsScene('admin');

    $snapA = TopologySnapshot::factory()->create();
    $snapB = TopologySnapshot::factory()->create();

    $component = Livewire::test(DocumentEditor::class)
        ->call('toggleSnapshot', $snapA->getKey())
        ->call('toggleSnapshot', $snapB->getKey())
        ->call('moveTopology', $snapA->getKey(), 1);

    expect(array_keys($component->get('topologiesItems')))
        ->toBe([$snapB->getKey(), $snapA->getKey()]);
});
