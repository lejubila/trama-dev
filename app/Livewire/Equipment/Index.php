<?php

declare(strict_types=1);

namespace App\Livewire\Equipment;

use App\Actions\Equipment\DuplicateEquipment;
use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Enums\HypervisorVendor;
use App\Livewire\Concerns\NotifiesGuide;
use App\Livewire\Concerns\RemembersFilters;
use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tag;
use App\Services\RackPlacementService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use NotifiesGuide, RemembersFilters, WithFileUploads, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $typeFilter = '';

    #[Url(except: 0)]
    public int $rackFilter = 0;

    #[Url(except: 0)]
    public int $siteFilter = 0;

    #[Url(except: 0)]
    public int $roomFilter = 0;

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: 0)]
    public int $tagFilter = 0;

    /** When ?edit=<equipmentId> lands on the page, auto-open the edit modal. */
    #[Url(as: 'edit')]
    public ?int $autoEditId = null;

    public bool $showForm = false;

    public ?int $editingId = null;

    /** Source device when the create form was opened via "Duplica". */
    public ?int $duplicatingFromId = null;

    public ?int $rackId = null;

    public ?int $roomId = null;

    public ?int $hostEquipmentId = null;

    public string $hypervisorVendor = '';

    public ?int $vcpu = null;

    public ?int $ramMb = null;

    public ?int $diskGb = null;

    public string $guestOs = '';

    public string $name = '';

    public string $type = 'switch';

    public string $vendor = '';

    public string $modelName = '';

    public string $serial = '';

    public string $firmware = '';

    public string $assetTag = '';

    public string $managementIp = '';

    public bool $mounted = false;

    public bool $locked = false;

    public ?int $positionUStart = null;

    public ?int $positionUHeight = 1;

    public string $positionOrient = 'front';

    public bool $onTop = false;

    public bool $hiddenInTopology = false;

    public string $status = 'active';

    public string $description = '';

    public $iconUpload = null;

    public ?string $existingIconPath = null;

    /** @var array<int, int|string> */
    public array $selectedTagIds = [];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rackId' => 'nullable|exists:racks,id',
            'roomId' => 'nullable|exists:rooms,id',
            'hostEquipmentId' => [
                'nullable',
                'integer',
                'exists:equipment,id',
                Rule::requiredIf(fn () => $this->type === EquipmentType::VirtualMachine->value),
            ],
            'hypervisorVendor' => ['nullable', Rule::in(array_column(HypervisorVendor::cases(), 'value'))],
            'vcpu' => 'nullable|integer|min:1|max:1024',
            'ramMb' => 'nullable|integer|min:1|max:8388608',
            'diskGb' => 'nullable|integer|min:1|max:1048576',
            'guestOs' => 'nullable|string|max:120',
            'name' => 'required|string|max:150',
            'type' => ['required', Rule::in(array_column(EquipmentType::cases(), 'value'))],
            'vendor' => 'nullable|string|max:80',
            'modelName' => 'nullable|string|max:120',
            'serial' => 'nullable|string|max:120',
            'firmware' => 'nullable|string|max:80',
            'assetTag' => 'nullable|string|max:80',
            'managementIp' => 'nullable|ip',
            'mounted' => 'boolean',
            'locked' => 'boolean',
            'positionUStart' => 'nullable|integer|min:1|max:60',
            'positionUHeight' => 'nullable|integer|min:1|max:60',
            'positionOrient' => 'required|in:front,rear',
            'onTop' => 'boolean',
            'hiddenInTopology' => 'boolean',
            'status' => ['required', Rule::in(array_column(EquipmentStatus::cases(), 'value'))],
            'description' => 'nullable|string|max:5000',
            'iconUpload' => 'nullable|image|max:5120',
            'selectedTagIds' => 'array',
            'selectedTagIds.*' => 'integer|exists:tags,id',
        ];
    }

    public function mount(): void
    {
        if ($this->autoEditId !== null) {
            $eq = Equipment::query()->find($this->autoEditId);
            if ($eq !== null && auth()->user()?->can('update', $eq)) {
                $this->openEdit($this->autoEditId);
            }
            $this->autoEditId = null;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingRackFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSiteFilter(): void
    {
        $this->roomFilter = 0;
        $this->rackFilter = 0;
        $this->resetPage();
    }

    public function updatingRoomFilter(): void
    {
        $this->rackFilter = 0;
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTagFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<int, string>
     */
    protected function rememberedFilters(): array
    {
        return ['search', 'typeFilter', 'siteFilter', 'roomFilter', 'rackFilter', 'statusFilter', 'tagFilter'];
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'typeFilter', 'siteFilter', 'roomFilter', 'rackFilter', 'statusFilter', 'tagFilter']);
        $this->persistFilters();
        $this->resetPage();
    }

    /**
     * Keep roomId synced with the rack's room whenever the user picks a
     * different rack in the form. Empty selection leaves the roomId
     * untouched so an admin can still pick a room manually after detaching
     * from a rack.
     */
    public function updatedRackId(mixed $value): void
    {
        if (! empty($value)) {
            $rack = Rack::query()->find((int) $value);
            if ($rack !== null) {
                $this->roomId = (int) $rack->room_id;
            }
        }
    }

    public function openCreate(): void
    {
        $this->authorize('create', Equipment::class);
        $this->reset(['editingId', 'duplicatingFromId', 'rackId', 'roomId', 'hostEquipmentId', 'hypervisorVendor', 'vcpu', 'ramMb', 'diskGb', 'guestOs', 'name', 'vendor', 'modelName', 'serial', 'firmware', 'assetTag', 'managementIp', 'mounted', 'locked', 'positionUStart', 'description', 'iconUpload', 'existingIconPath', 'onTop', 'hiddenInTopology', 'selectedTagIds']);
        $this->type = 'switch';
        $this->status = 'active';
        $this->positionUHeight = 1;
        $this->positionOrient = 'front';
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $eq = Equipment::query()->findOrFail($id);
        $this->authorize('update', $eq);

        $this->fillFormFrom($eq);
        $this->editingId = $eq->getKey();
        $this->duplicatingFromId = null;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    /**
     * Opens the create form pre-filled from an existing device. Nothing is
     * persisted until the user saves; interfaces are copied at that point.
     */
    public function openDuplicate(int $id, DuplicateEquipment $duplicator): void
    {
        $eq = Equipment::query()->findOrFail($id);
        $this->authorize('view', $eq);
        $this->authorize('create', Equipment::class);

        $this->fillFormFrom($eq);
        $this->editingId = null;
        $this->duplicatingFromId = $eq->getKey();
        $this->name = $duplicator->nextName($eq);
        // Identifiers of the physical unit must not be cloned.
        $this->serial = '';
        $this->assetTag = '';
        $this->managementIp = '';
        // The source's slot is taken: keep rack/height/side, let the user pick the U.
        $this->positionUStart = null;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    private function fillFormFrom(Equipment $eq): void
    {
        $this->rackId = $eq->rack_id;
        $this->roomId = $eq->room_id;
        $this->hostEquipmentId = $eq->host_equipment_id;
        $cf = is_array($eq->custom_fields) ? $eq->custom_fields : [];
        $this->hypervisorVendor = (string) ($cf['hypervisor_vendor'] ?? '');
        $this->vcpu = isset($cf['vcpu']) ? (int) $cf['vcpu'] : null;
        $this->ramMb = isset($cf['ram_mb']) ? (int) $cf['ram_mb'] : null;
        $this->diskGb = isset($cf['disk_gb']) ? (int) $cf['disk_gb'] : null;
        $this->guestOs = (string) ($cf['guest_os'] ?? '');
        $this->name = $eq->name;
        $this->type = $eq->type?->value ?? 'switch';
        $this->vendor = (string) ($eq->vendor ?? '');
        $this->modelName = (string) ($eq->model ?? '');
        $this->serial = (string) ($eq->serial ?? '');
        $this->firmware = (string) ($eq->firmware ?? '');
        $this->assetTag = (string) ($eq->asset_tag ?? '');
        $this->managementIp = (string) ($eq->management_ip ?? '');
        $this->mounted = (bool) $eq->mounted;
        $this->locked = (bool) $eq->locked;
        $this->positionUStart = $eq->position_u_start;
        $this->positionUHeight = $eq->position_u_height ?? 1;
        $this->positionOrient = $eq->position_orient ?: 'front';
        $this->onTop = (bool) $eq->on_top;
        $this->hiddenInTopology = (bool) $eq->hidden_in_topology;
        $this->status = $eq->status?->value ?? 'active';
        $this->description = (string) ($eq->description ?? '');
        $this->iconUpload = null;
        $this->existingIconPath = $eq->icon_path;
        $this->selectedTagIds = $eq->tags()->pluck('tags.id')->all();
    }

    public function save(RackPlacementService $placement): void
    {
        $this->validate();

        $isVm = $this->type === EquipmentType::VirtualMachine->value;
        $isHypervisor = $this->type === EquipmentType::Hypervisor->value;

        if ($isVm) {
            // VM non sono rack-mounted: forziamo a null tutte le coordinate fisiche.
            $this->mounted = false;
            $this->rackId = null;
            $this->positionUStart = null;
            $this->onTop = false;

            // L'host deve essere un hypervisor dello stesso tenant.
            $host = Equipment::query()->find($this->hostEquipmentId);
            if ($host === null || $host->type !== EquipmentType::Hypervisor) {
                $this->addError('hostEquipmentId', __('equipment.err_host_not_hypervisor'));

                return;
            }
            // Eredita la sede/locale dell'host per coerenza nei filtri.
            if ($this->roomId === null) {
                $this->roomId = $host->room_id ?? $host->rack?->room_id;
            }
        } else {
            $this->hostEquipmentId = null;
        }

        if ($this->mounted) {
            if ($this->rackId === null) {
                $this->addError('rackId', __('equipment.err_mounted_needs_rack'));

                return;
            }

            if (! $this->onTop) {
                if ($this->positionUStart === null || $this->positionUHeight === null) {
                    $this->addError('positionUStart', __('equipment.err_u_required'));

                    return;
                }

                $rack = Rack::query()->findOrFail($this->rackId);
                $excluding = $this->editingId ? Equipment::query()->find($this->editingId) : null;

                if (! $placement->canPlace($rack, $this->positionUStart, $this->positionUHeight, $excluding, $this->positionOrient)) {
                    $this->addError('positionUStart', __('equipment.err_u_conflict'));

                    return;
                }
            }
        }

        // U coordinates are only meaningful for rack-mounted devices that
        // actually occupy a slot. On-top devices reuse the rack association
        // but have NULL Us so they don't clash with anything in the layout.
        $useUs = $this->mounted && ! $this->onTop;

        // When the device sits in a rack, the rack's room is authoritative:
        // re-derive room_id here so it can't drift out of sync with the rack.
        // Unracked devices keep whatever room the user selected manually.
        if ($this->mounted && $this->rackId !== null) {
            $rackForRoom = Rack::query()->find($this->rackId);
            if ($rackForRoom !== null) {
                $this->roomId = (int) $rackForRoom->room_id;
            }
        }

        $customFields = [];
        if ($isHypervisor && $this->hypervisorVendor !== '') {
            $customFields['hypervisor_vendor'] = $this->hypervisorVendor;
        }
        if ($isVm) {
            if ($this->vcpu !== null) {
                $customFields['vcpu'] = $this->vcpu;
            }
            if ($this->ramMb !== null) {
                $customFields['ram_mb'] = $this->ramMb;
            }
            if ($this->diskGb !== null) {
                $customFields['disk_gb'] = $this->diskGb;
            }
            if ($this->guestOs !== '') {
                $customFields['guest_os'] = $this->guestOs;
            }
        }

        $payload = [
            'rack_id' => $this->rackId,
            'room_id' => $this->roomId,
            'host_equipment_id' => $this->hostEquipmentId,
            'name' => $this->name,
            'type' => EquipmentType::from($this->type),
            'vendor' => $this->vendor !== '' ? $this->vendor : null,
            'model' => $this->modelName !== '' ? $this->modelName : null,
            'serial' => $this->serial !== '' ? $this->serial : null,
            'firmware' => $this->firmware !== '' ? $this->firmware : null,
            'asset_tag' => $this->assetTag !== '' ? $this->assetTag : null,
            'management_ip' => $this->managementIp !== '' ? $this->managementIp : null,
            'mounted' => $this->mounted,
            'locked' => $this->locked,
            'on_top' => $this->mounted ? $this->onTop : false,
            'hidden_in_topology' => $this->hiddenInTopology,
            'position_u_start' => $useUs ? $this->positionUStart : null,
            'position_u_height' => $useUs ? $this->positionUHeight : null,
            'position_orient' => $this->mounted ? $this->positionOrient : null,
            'status' => EquipmentStatus::from($this->status),
            'description' => $this->description !== '' ? $this->description : null,
        ];

        // Merge dei custom_fields preservando eventuali chiavi esistenti
        // non gestite dal form (es. metadata importati altrove).
        $source = $this->editingId === null && $this->duplicatingFromId !== null
            ? Equipment::query()->findOrFail($this->duplicatingFromId)
            : null;

        if ($this->editingId !== null || $source !== null) {
            $existing = $source ?? Equipment::query()->find($this->editingId);
            $prev = is_array($existing?->custom_fields) ? $existing->custom_fields : [];
            foreach (['hypervisor_vendor', 'vcpu', 'ram_mb', 'disk_gb', 'guest_os'] as $managedKey) {
                unset($prev[$managedKey]);
            }
            $payload['custom_fields'] = array_merge($prev, $customFields);
        } else {
            $payload['custom_fields'] = $customFields;
        }

        if ($this->editingId !== null) {
            $eq = Equipment::query()->findOrFail($this->editingId);
            $this->authorize('update', $eq);

            if ($this->iconUpload instanceof TemporaryUploadedFile) {
                $payload['icon_path'] = $this->storeIcon($this->iconUpload, $eq->icon_path, (int) $eq->tenant_id);
            }

            $eq->update($payload);
            $this->dispatch('toast', type: 'success', message: __('equipment.toast_updated'));
        } else {
            $this->authorize('create', Equipment::class);

            if ($source !== null) {
                $this->authorize('view', $source);
                $payload['icon_size_px'] = $source->icon_size_px;
                [$eq, $copied] = DB::transaction(function () use ($payload, $source): array {
                    $eq = Equipment::create($payload);

                    return [$eq, app(DuplicateEquipment::class)->copyInterfaces($source, $eq)];
                });
            } else {
                $eq = Equipment::create($payload);
            }

            if ($this->iconUpload instanceof TemporaryUploadedFile) {
                $eq->update(['icon_path' => $this->storeIcon($this->iconUpload, null, (int) $eq->tenant_id)]);
            } elseif ($source !== null && $this->existingIconPath !== null && $source->icon_path !== null) {
                $eq->update(['icon_path' => $this->copyIcon($source->icon_path, (int) $eq->tenant_id)]);
            }

            $this->dispatch('toast', type: 'success', message: $source !== null
                ? __('equipment.toast_duplicated', ['count' => $copied])
                : __('equipment.toast_created'));
            $this->duplicatingFromId = null;
            $this->guideCreated('equipment', $eq);
        }

        $eq->tags()->sync($this->selectedTagIds);

        $this->showForm = false;
    }

    public function removeIcon(): void
    {
        if ($this->editingId === null) {
            $this->iconUpload = null;
            // In duplicate mode this also stops the source's icon being copied.
            $this->existingIconPath = null;

            return;
        }

        $eq = Equipment::query()->findOrFail($this->editingId);
        $this->authorize('update', $eq);

        if ($eq->icon_path !== null) {
            Storage::disk('public')->delete($eq->icon_path);
            $eq->update(['icon_path' => null]);
        }

        $this->iconUpload = null;
        $this->existingIconPath = null;
        $this->dispatch('toast', type: 'success', message: __('common.icon_removed'));
    }

    /**
     * Flip the `hidden_in_topology` flag straight from the index row.
     * The eye icon next to the device name doubles as a quick toggle.
     */
    public function toggleHiddenInTopology(int $id): void
    {
        $eq = Equipment::query()->findOrFail($id);
        $this->authorize('update', $eq);

        $eq->update(['hidden_in_topology' => ! $eq->hidden_in_topology]);
        $this->dispatch(
            'toast',
            type: 'success',
            message: $eq->hidden_in_topology
                ? __('equipment.toast_hidden')
                : __('equipment.toast_visible'),
        );
    }

    public function delete(int $id): void
    {
        $eq = Equipment::query()->findOrFail($id);
        $this->authorize('delete', $eq);

        if ($eq->icon_path !== null) {
            Storage::disk('public')->delete($eq->icon_path);
        }

        $eq->delete();
        $this->dispatch('toast', type: 'success', message: __('equipment.toast_deleted'));
    }

    /**
     * Each device owns its icon file (delete() removes it), so a duplicate
     * gets its own copy instead of sharing the source's path.
     */
    private function copyIcon(string $sourcePath, int $tenantId): ?string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($sourcePath)) {
            return null;
        }

        $ext = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $path = "icons/{$tenantId}/equipment/".Str::random(40).($ext !== '' ? ".{$ext}" : '');
        $disk->copy($sourcePath, $path);

        return $path;
    }

    private function storeIcon(TemporaryUploadedFile $file, ?string $previousPath, int $tenantId): string
    {
        $path = $file->store("icons/{$tenantId}/equipment", 'public');

        if ($previousPath !== null && $previousPath !== $path) {
            Storage::disk('public')->delete($previousPath);
        }

        return $path;
    }

    public function render(): View
    {
        $equipment = Equipment::query()
            ->with(['rack.room.site', 'room.site', 'tags', 'host'])
            ->withCount('interfaces')
            ->when($this->search !== '', fn ($q) => $q->where(function ($qq) {
                $qq->where('name', 'ilike', "%{$this->search}%")
                    ->orWhere('serial', 'ilike', "%{$this->search}%")
                    ->orWhere('model', 'ilike', "%{$this->search}%");
            }))
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->rackFilter > 0, fn ($q) => $q->where('rack_id', $this->rackFilter))
            ->when($this->roomFilter > 0, fn ($q) => $q->where(function ($qq) {
                $qq->where('room_id', $this->roomFilter)
                    ->orWhereHas('rack', fn ($qr) => $qr->where('room_id', $this->roomFilter));
            }))
            ->when($this->siteFilter > 0, fn ($q) => $q->where(function ($qq) {
                $qq->whereHas('room', fn ($qr) => $qr->where('site_id', $this->siteFilter))
                    ->orWhereHas('rack.room', fn ($qr) => $qr->where('site_id', $this->siteFilter));
            }))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->tagFilter > 0, fn ($q) => $q->whereHas('tags', fn ($qt) => $qt->where('tags.id', $this->tagFilter)))
            ->orderBy('name')
            ->paginate(20);

        $allRooms = Room::query()->with('site')->orderBy('name')->get();
        $allRacks = Rack::query()->with('room')->orderBy('name')->get();

        $filterRooms = $this->siteFilter > 0
            ? $allRooms->filter(fn ($r) => (int) ($r->site_id ?? 0) === $this->siteFilter)->values()
            : $allRooms;

        $filterRacks = $allRacks;
        if ($this->roomFilter > 0) {
            $filterRacks = $filterRacks->filter(fn ($r) => (int) ($r->room_id ?? 0) === $this->roomFilter)->values();
        } elseif ($this->siteFilter > 0) {
            $roomIds = $filterRooms->pluck('id')->all();
            $filterRacks = $filterRacks->filter(fn ($r) => in_array((int) ($r->room_id ?? 0), $roomIds, true))->values();
        }

        return view('livewire.equipment.index', [
            'equipment' => $equipment,
            'racks' => $allRacks,
            'rooms' => $allRooms,
            'sites' => Site::query()->orderBy('name')->get(),
            'filterRooms' => $filterRooms,
            'filterRacks' => $filterRacks,
            'types' => EquipmentType::cases(),
            'statuses' => EquipmentStatus::cases(),
            'allTags' => Tag::query()->orderBy('name')->get(),
            'hypervisorOptions' => Equipment::query()
                ->where('type', EquipmentType::Hypervisor->value)
                ->when($this->editingId !== null, fn ($q) => $q->where('id', '!=', $this->editingId))
                ->orderBy('name')
                ->get(['id', 'name']),
            'hypervisorVendors' => HypervisorVendor::cases(),
            'duplicatingInterfaceCount' => $this->duplicatingFromId !== null
                ? (int) Equipment::query()->find($this->duplicatingFromId)?->interfaces()->count()
                : 0,
        ]);
    }
}
