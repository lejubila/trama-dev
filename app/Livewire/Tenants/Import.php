<?php

declare(strict_types=1);

namespace App\Livewire\Tenants;

use App\Actions\Tenancy\CreateTenant;
use App\Models\Tenant;
use App\Services\TenantTransfer\InvalidTenantArchiveException;
use App\Services\TenantTransfer\TenantArchive;
use App\Services\TenantTransfer\TenantArchiveImporter;
use App\Services\TenantTransfer\TenantArchiveReader;
use App\Services\TenantTransfer\TenantArchiveSchema;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Two-step import of a tenant archive: upload → preview (with the client's
 * data editable) → import as a NEW tenant. Existing tenants are never touched.
 */
#[Layout('layouts.app')]
class Import extends Component
{
    use WithFileUploads;

    public int $step = 1;

    /** @var TemporaryUploadedFile|null */
    public $archive = null;

    public string $name = '';

    public string $slug = '';

    public string $domain = '';

    /** @var array{name: string, slug: string, exported_at: string|null} */
    public array $source = ['name' => '', 'slug' => '', 'exported_at' => null];

    /** @var array<string, int> */
    public array $counts = [];

    public ?string $importError = null;

    public function mount(): void
    {
        $this->authorize('create', Tenant::class);
    }

    public function toPreview(TenantArchiveReader $reader, CreateTenant $createTenant): void
    {
        $this->authorize('create', Tenant::class);
        $this->validate(['archive' => 'required|file|mimes:zip|max:30720']);

        try {
            $archive = $reader->read($this->archive->getRealPath());
        } catch (InvalidTenantArchiveException $e) {
            $this->addError('archive', $e->getMessage());

            return;
        }

        $tenant = $archive->tenant();
        $archive->close();

        $this->source = ['name' => $tenant['name'], 'slug' => $tenant['slug'], 'exported_at' => $this->exportedAt($archive)];
        $this->counts = $archive->counts();
        $this->name = $tenant['name'];
        // Suggest a free slug up front so the default choice just works.
        $this->slug = $createTenant->uniqueSlugFor($tenant['slug'] !== '' ? $tenant['slug'] : $tenant['name']);
        $this->domain = $tenant['domain'] ?? '';
        $this->importError = null;
        $this->resetErrorBag();
        $this->step = 2;
    }

    public function runImport(TenantArchiveReader $reader, TenantArchiveImporter $importer): void
    {
        $this->authorize('create', Tenant::class);
        $this->validate([
            'name' => 'required|string|max:150',
            'slug' => 'required|string|alpha_dash|max:80|unique:tenants,slug',
            'domain' => 'nullable|string|max:255',
        ]);

        $archive = null;
        try {
            $archive = $reader->read($this->archive->getRealPath());
            $tenant = $importer->import($archive, auth()->user(), [
                'name' => $this->name,
                'slug' => $this->slug,
                'domain' => $this->domain !== '' ? $this->domain : null,
            ]);
        } catch (InvalidTenantArchiveException $e) {
            $this->importError = $e->getMessage();

            return;
        } catch (Throwable $e) {
            Log::error('Tenant archive import failed', ['exception' => $e]);
            $this->importError = 'Import non riuscito: il file contiene dati non compatibili. Nessun dato è stato importato.';

            return;
        } finally {
            $archive?->close();
        }

        TenantContext::setId((int) $tenant->getKey());
        $this->dispatch('toast', type: 'success', message: "Cliente \"{$tenant->name}\" importato.");
        $this->redirectRoute('tenants.manage', ['tenant' => $tenant], navigate: true);
    }

    public function restart(): void
    {
        $this->reset(['step', 'archive', 'name', 'slug', 'domain', 'source', 'counts', 'importError']);
        $this->resetErrorBag();
    }

    private function exportedAt(TenantArchive $archive): ?string
    {
        $raw = $archive->exportedAt();
        if ($raw === null) {
            return null;
        }

        try {
            return Carbon::parse($raw)->timezone(config('app.timezone'))->format('d/m/Y H:i');
        } catch (Throwable) {
            return $raw;
        }
    }

    public function render(): View
    {
        return view('livewire.tenants.import', [
            'labels' => TenantArchiveSchema::labels(),
        ]);
    }
}
