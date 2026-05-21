<?php

declare(strict_types=1);

namespace App\Livewire\Icons;

use App\Enums\EquipmentType;
use App\Models\DeviceIcon;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithFileUploads;

    /**
     * Pending uploads for global icons, keyed by kind.
     *
     * @var array<string, TemporaryUploadedFile|null>
     */
    public array $globalUpload = [];

    /**
     * Pending uploads for tenant-scoped icons, keyed by kind.
     *
     * @var array<string, TemporaryUploadedFile|null>
     */
    public array $tenantUpload = [];

    public function mount(): void
    {
        $this->authorize('viewAny', DeviceIcon::class);
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function kinds(): array
    {
        $kinds = [['key' => 'rack', 'label' => 'Rack']];
        foreach (EquipmentType::cases() as $case) {
            $kinds[] = ['key' => $case->value, 'label' => $case->label()];
        }

        return $kinds;
    }

    public function saveGlobal(string $kind): void
    {
        $this->authorize('manageGlobal', DeviceIcon::class);
        $this->validateKind($kind);

        $file = $this->globalUpload[$kind] ?? null;
        if (! $file instanceof TemporaryUploadedFile) {
            $this->addError("globalUpload.$kind", 'Seleziona un file.');

            return;
        }

        $this->validate(["globalUpload.$kind" => 'image|max:5120']);

        $existing = DeviceIcon::query()->whereNull('tenant_id')->where('kind', $kind)->first();
        $path = $file->store('icons/global', 'public');
        if ($existing !== null && $existing->image_path !== $path) {
            Storage::disk('public')->delete($existing->image_path);
        }

        DeviceIcon::query()->updateOrCreate(
            ['tenant_id' => null, 'kind' => $kind],
            ['image_path' => $path],
        );

        $this->globalUpload[$kind] = null;
        $this->dispatch('toast', type: 'success', message: 'Icona globale aggiornata.');
    }

    public function saveTenant(string $kind): void
    {
        $this->authorize('manage', DeviceIcon::class);
        $this->validateKind($kind);

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            $this->addError("tenantUpload.$kind", 'Nessun cliente attivo.');

            return;
        }

        $file = $this->tenantUpload[$kind] ?? null;
        if (! $file instanceof TemporaryUploadedFile) {
            $this->addError("tenantUpload.$kind", 'Seleziona un file.');

            return;
        }

        $this->validate(["tenantUpload.$kind" => 'image|max:5120']);

        $existing = DeviceIcon::query()->where('tenant_id', $tenantId)->where('kind', $kind)->first();
        $path = $file->store("icons/{$tenantId}", 'public');
        if ($existing !== null && $existing->image_path !== $path) {
            Storage::disk('public')->delete($existing->image_path);
        }

        DeviceIcon::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'kind' => $kind],
            ['image_path' => $path],
        );

        $this->tenantUpload[$kind] = null;
        $this->dispatch('toast', type: 'success', message: 'Override per il cliente aggiornato.');
    }

    public function removeGlobal(string $kind): void
    {
        $this->authorize('manageGlobal', DeviceIcon::class);
        $this->deleteIconRow(null, $kind);
        $this->dispatch('toast', type: 'success', message: 'Icona globale rimossa.');
    }

    public function removeTenant(string $kind): void
    {
        $this->authorize('manage', DeviceIcon::class);
        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            return;
        }
        $this->deleteIconRow($tenantId, $kind);
        $this->dispatch('toast', type: 'success', message: 'Override rimosso.');
    }

    private function deleteIconRow(?int $tenantId, string $kind): void
    {
        $query = DeviceIcon::query()->where('kind', $kind);
        if ($tenantId === null) {
            $query->whereNull('tenant_id');
        } else {
            $query->where('tenant_id', $tenantId);
        }

        $icon = $query->first();
        if ($icon === null) {
            return;
        }

        Storage::disk('public')->delete($icon->image_path);
        $icon->delete();
    }

    private function validateKind(string $kind): void
    {
        $valid = array_map(fn ($k) => $k['key'], $this->kinds());
        abort_unless(in_array($kind, $valid, true), 422);
    }

    private function currentTenantId(): ?int
    {
        return auth()->user()?->current_tenant_id;
    }

    public function render(): View
    {
        $tenantId = $this->currentTenantId();
        $tenant = $tenantId !== null ? Tenant::query()->find($tenantId) : null;

        $rows = DeviceIcon::query()
            ->when(true, function ($q) use ($tenantId): void {
                $q->whereNull('tenant_id');
                if ($tenantId !== null) {
                    $q->orWhere('tenant_id', $tenantId);
                }
            })
            ->get(['tenant_id', 'kind', 'image_path']);

        $globalByKind = [];
        $tenantByKind = [];
        foreach ($rows as $row) {
            if ($row->tenant_id === null) {
                $globalByKind[$row->kind] = $row->image_path;
            } elseif ((int) $row->tenant_id === $tenantId) {
                $tenantByKind[$row->kind] = $row->image_path;
            }
        }

        return view('livewire.icons.index', [
            'kinds' => $this->kinds(),
            'globalByKind' => $globalByKind,
            'tenantByKind' => $tenantByKind,
            'tenant' => $tenant,
        ]);
    }
}
