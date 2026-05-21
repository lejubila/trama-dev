<?php

declare(strict_types=1);

namespace App\Livewire\Rooms;

use App\Models\Rack;
use App\Models\Room;
use App\Services\Icons\IconResolver;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Map extends Component
{
    public Room $room;

    /**
     * Fixed scale for the floor plan: 50 SVG units per meter.
     * The SVG viewBox is computed from the room's width_m/depth_m (or a
     * default 12×8 m canvas when dimensions are not set). The container CSS
     * (w-full) scales the rendering to fit the page.
     */
    public const SCALE = 50;

    public const DEFAULT_WIDTH_M = 12.0;

    public const DEFAULT_DEPTH_M = 8.0;

    /** Footprint of a rack on the floor plan, in SVG units. */
    public const RACK_PX = 40;

    /** Default visual icon size in screen pixels (kept constant by JS). */
    public const DEFAULT_ICON_SIZE_PX = 40;

    public const MIN_ICON_SIZE_PX = 16;

    public const MAX_ICON_SIZE_PX = 200;

    public function mount(Room $room): void
    {
        $this->room = $room;

        $user = auth()->user();
        if ($user !== null && $user->canManageData()) {
            // Default position = center of an icon placed flush against the
            // top-left corner of the room — i.e. half an icon away from each
            // wall. With SCALE=50 and default icon=40px this is 0.4m.
            $defaultCenter = (self::DEFAULT_ICON_SIZE_PX / self::SCALE) / 2;

            Rack::query()
                ->where('room_id', $room->getKey())
                ->where(function ($q): void {
                    $q->whereNull('position_x')->orWhereNull('position_y');
                })
                ->update(['position_x' => $defaultCenter, 'position_y' => $defaultCenter]);
        }
    }

    #[On('room-updated')]
    public function onRoomUpdated(int $roomId): void
    {
        if ($roomId !== $this->room->getKey()) {
            $this->skipRender();
        }
    }

    public function setRoomIconSize(int $sizePx): void
    {
        $this->authorize('update', $this->room);

        $clamped = max(self::MIN_ICON_SIZE_PX, min(self::MAX_ICON_SIZE_PX, $sizePx));
        $this->room->update(['rack_icon_size_px' => $clamped]);
        $this->dispatch('room-updated', roomId: $this->room->getKey());
    }

    public function resetRackIcon(int $rackId): void
    {
        /** @var Rack $rack */
        $rack = Rack::query()
            ->where('room_id', $this->room->getKey())
            ->findOrFail($rackId);

        $this->authorize('update', $rack);
        $rack->update(['icon_size_px' => null]);
    }

    public function resetAllRackIcons(): void
    {
        $this->authorize('update', $this->room);

        Rack::query()
            ->where('room_id', $this->room->getKey())
            ->whereNotNull('icon_size_px')
            ->update(['icon_size_px' => null]);
    }

    public function resizeRackIcon(int $rackId, int $sizePx): void
    {
        /** @var Rack $rack */
        $rack = Rack::query()
            ->where('room_id', $this->room->getKey())
            ->findOrFail($rackId);

        $this->authorize('update', $rack);

        $clamped = max(self::MIN_ICON_SIZE_PX, min(self::MAX_ICON_SIZE_PX, $sizePx));
        $rack->update(['icon_size_px' => $clamped]);
    }

    public function moveRack(int $rackId, float $x, float $y): void
    {
        /** @var Rack $rack */
        $rack = Rack::query()
            ->where('room_id', $this->room->getKey())
            ->findOrFail($rackId);

        $this->authorize('update', $rack);

        [$widthM, $depthM] = $this->dimensions();
        // Clamp the CENTER so the icon stays fully inside the room.
        // Server-side size is approximated from icon_size_px in SVG units;
        // the client-side clamp uses the exact rendered pixel size.
        $iconSizePx = (int) ($rack->icon_size_px ?? self::DEFAULT_ICON_SIZE_PX);
        $halfM = ($iconSizePx / self::SCALE) / 2;

        $rack->update([
            'position_x' => round(max($halfM, min($widthM - $halfM, $x)), 2),
            'position_y' => round(max($halfM, min($depthM - $halfM, $y)), 2),
        ]);
    }

    public function render(IconResolver $resolver): View
    {
        $room = Room::query()->findOrFail($this->room->getKey());
        $this->room = $room;

        $racks = $room->racks()
            ->orderBy('name')
            ->get(['id', 'name', 'position_x', 'position_y', 'icon_path', 'icon_size_px']);

        $tenantId = (int) ($room->tenant_id ?? 0);
        $roomDefault = $room->rack_icon_size_px !== null
            ? (int) $room->rack_icon_size_px
            : self::DEFAULT_ICON_SIZE_PX;

        $rackIcons = [];
        $rackIconSizes = [];
        $rackHasOverride = [];
        foreach ($racks as $r) {
            $rackIcons[$r->id] = $resolver->urlForRack($r, $tenantId);
            $rackHasOverride[$r->id] = $r->icon_size_px !== null;
            $rackIconSizes[$r->id] = $r->icon_size_px !== null
                ? (int) $r->icon_size_px
                : $roomDefault;
        }

        $user = auth()->user();
        $canEdit = $user !== null && $user->canManageData();

        [$widthM, $depthM] = $this->dimensions();

        return view('livewire.rooms.map', [
            'room' => $room,
            'racks' => $racks,
            'rackIcons' => $rackIcons,
            'rackIconSizes' => $rackIconSizes,
            'rackHasOverride' => $rackHasOverride,
            'roomIconSize' => $roomDefault,
            'canEdit' => $canEdit,
            'widthM' => $widthM,
            'depthM' => $depthM,
            'hasCustomDimensions' => $room->width_m !== null && $room->depth_m !== null,
        ]);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function dimensions(): array
    {
        $widthM = $this->room->width_m !== null ? (float) $this->room->width_m : self::DEFAULT_WIDTH_M;
        $depthM = $this->room->depth_m !== null ? (float) $this->room->depth_m : self::DEFAULT_DEPTH_M;

        return [$widthM, $depthM];
    }
}
