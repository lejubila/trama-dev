<?php

declare(strict_types=1);

namespace App\Livewire\Sites;

use App\Models\Room;
use App\Models\Site;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Show extends Component
{
    use WithFileUploads;

    public Site $site;

    public bool $showRoomForm = false;

    public ?int $editingRoomId = null;

    #[Validate('required|string|max:150')]
    public string $roomName = '';

    #[Validate('nullable|string|max:50')]
    public string $roomFloor = '';

    #[Validate('nullable|numeric|min:0.5|max:200')]
    public ?float $roomWidthM = null;

    #[Validate('nullable|numeric|min:0.5|max:200')]
    public ?float $roomDepthM = null;

    /** Temporary uploaded image (when the admin picks a new floor-plan). */
    #[Validate('nullable|image|max:5120')]
    public $roomFloorPlan = null;

    /** Existing stored path for the floor-plan image, when editing. */
    public ?string $existingFloorPlanPath = null;

    #[Validate('nullable|string|max:2000')]
    public string $roomNotes = '';

    public function mount(Site $site): void
    {
        $this->authorize('view', $site);
        $this->site = $site;
    }

    public function openRoomCreate(): void
    {
        $this->authorize('create', Room::class);
        $this->reset(['editingRoomId', 'roomName', 'roomFloor', 'roomNotes', 'roomWidthM', 'roomDepthM', 'roomFloorPlan', 'existingFloorPlanPath']);
        $this->resetErrorBag();
        $this->showRoomForm = true;
    }

    public function openRoomEdit(int $id): void
    {
        $room = Room::query()->findOrFail($id);
        $this->authorize('update', $room);

        $this->editingRoomId = $room->getKey();
        $this->roomName = $room->name;
        $this->roomFloor = (string) ($room->floor ?? '');
        $this->roomNotes = (string) ($room->notes ?? '');
        $this->roomWidthM = $room->width_m !== null ? (float) $room->width_m : null;
        $this->roomDepthM = $room->depth_m !== null ? (float) $room->depth_m : null;
        $this->roomFloorPlan = null;
        $this->existingFloorPlanPath = $room->floor_plan_path;
        $this->resetErrorBag();
        $this->showRoomForm = true;
    }

    public function saveRoom(): void
    {
        $this->validate();

        $payload = [
            'site_id' => $this->site->getKey(),
            'name' => $this->roomName,
            'floor' => $this->roomFloor !== '' ? $this->roomFloor : null,
            'width_m' => $this->roomWidthM,
            'depth_m' => $this->roomDepthM,
            'notes' => $this->roomNotes !== '' ? $this->roomNotes : null,
        ];

        if ($this->editingRoomId !== null) {
            $room = Room::query()->findOrFail($this->editingRoomId);
            $this->authorize('update', $room);

            if ($this->roomFloorPlan instanceof TemporaryUploadedFile) {
                $payload['floor_plan_path'] = $this->storeFloorPlan($this->roomFloorPlan, $room->floor_plan_path);
            }

            $room->update($payload);
            $this->dispatch('room-updated', roomId: $room->getKey());
            $this->dispatch('toast', type: 'success', message: __('sites.room_toast_updated'));
        } else {
            $this->authorize('create', Room::class);

            if ($this->roomFloorPlan instanceof TemporaryUploadedFile) {
                $payload['floor_plan_path'] = $this->storeFloorPlan($this->roomFloorPlan, null);
            }

            Room::create($payload);
            $this->dispatch('toast', type: 'success', message: __('sites.room_toast_created'));
        }

        $this->showRoomForm = false;
    }

    public function removeFloorPlan(): void
    {
        if ($this->editingRoomId === null) {
            $this->roomFloorPlan = null;

            return;
        }

        $room = Room::query()->findOrFail($this->editingRoomId);
        $this->authorize('update', $room);

        if ($room->floor_plan_path !== null) {
            Storage::disk('public')->delete($room->floor_plan_path);
            $room->update(['floor_plan_path' => null]);
            $this->dispatch('room-updated', roomId: $room->getKey());
        }

        $this->roomFloorPlan = null;
        $this->existingFloorPlanPath = null;
        $this->dispatch('toast', type: 'success', message: __('sites.floorplan_removed'));
    }

    public function deleteRoom(int $id): void
    {
        $room = Room::query()->findOrFail($id);
        $this->authorize('delete', $room);

        if ($room->floor_plan_path !== null) {
            Storage::disk('public')->delete($room->floor_plan_path);
        }

        $room->delete();
        $this->dispatch('toast', type: 'success', message: __('sites.room_toast_deleted'));
    }

    private function storeFloorPlan(TemporaryUploadedFile $file, ?string $previousPath): string
    {
        $tenantId = (int) ($this->site->tenant_id ?? 0);
        $path = $file->store("floor-plans/{$tenantId}", 'public');

        if ($previousPath !== null && $previousPath !== $path) {
            Storage::disk('public')->delete($previousPath);
        }

        return $path;
    }

    public function render(): View
    {
        return view('livewire.sites.show', [
            'rooms' => $this->site->rooms()->withCount('racks')->orderBy('name')->get(),
        ]);
    }
}
