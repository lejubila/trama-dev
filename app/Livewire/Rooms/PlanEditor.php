<?php

declare(strict_types=1);

namespace App\Livewire\Rooms;

use App\Livewire\Rooms\Map as RoomMap;
use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Services\Icons\IconResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class PlanEditor extends Component
{
    public Room $room;

    /**
     * The drawing payload. Coordinates are in meters (same reference
     * frame as rack/equipment positions). Shape:
     *
     *   [
     *     'version' => 1,
     *     'walls'   => [ ['id'=>uuid, 'points'=>[[x,y],…], 'thickness_m'=>float], … ],
     *     'doors'   => [ ['id'=>uuid, 'wall_id'=>uuid, 't'=>0..1, 'width_m'=>float, 'swing'=>string], … ],
     *     'windows' => [ ['id'=>uuid, 'wall_id'=>uuid, 't'=>0..1, 'width_m'=>float], … ],
     *     'labels'  => [ ['id'=>uuid, 'pos'=>[x,y], 'text'=>string], … ],
     *   ]
     *
     * @var array<string, mixed>
     */
    public array $drawing = [
        'version' => 1,
        'walls' => [],
        'doors' => [],
        'windows' => [],
        'labels' => [],
    ];

    public function mount(Room $room): void
    {
        $this->authorize('update', $room);
        $this->room = $room->loadMissing('site');

        $existing = $room->floor_plan_drawing;
        if (is_array($existing)) {
            $this->drawing = $this->normalize($existing);
        }
    }

    /**
     * Persist the drawing payload coming from the Alpine editor.
     *
     * @param  array<string, mixed>  $payload
     */
    public function savePlan(array $payload): void
    {
        $this->authorize('update', $this->room);

        $normalized = $this->normalize($payload);
        $this->drawing = $normalized;
        $this->room->update(['floor_plan_drawing' => $normalized]);
        $this->dispatch('room-updated', roomId: $this->room->getKey());
        $this->dispatch('toast', type: 'success', message: __('rooms.plan_editor_saved'));
    }

    public function clearPlan(): void
    {
        $this->authorize('update', $this->room);

        $this->drawing = [
            'version' => 1,
            'walls' => [],
            'doors' => [],
            'windows' => [],
            'labels' => [],
        ];
        $this->room->update(['floor_plan_drawing' => null]);
        $this->dispatch('room-updated', roomId: $this->room->getKey());
        $this->dispatch('toast', type: 'success', message: __('rooms.plan_editor_cleared'));
    }

    /**
     * Defensive normalization: keep only the known shape, drop anything
     * unexpected so a malicious payload can't smuggle arbitrary JSON
     * into the DB.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(array $payload): array
    {
        $walls = [];
        foreach ((array) ($payload['walls'] ?? []) as $w) {
            if (! is_array($w)) {
                continue;
            }
            $points = [];
            foreach ((array) ($w['points'] ?? []) as $p) {
                if (is_array($p) && count($p) === 2) {
                    $points[] = [(float) $p[0], (float) $p[1]];
                }
            }
            if (count($points) < 2) {
                continue;
            }
            $walls[] = [
                'id' => (string) ($w['id'] ?? uniqid('w', true)),
                'points' => $points,
                'thickness_m' => max(0.05, min(1.0, (float) ($w['thickness_m'] ?? 0.15))),
            ];
        }

        $wallIds = array_flip(array_column($walls, 'id'));
        $anchored = function (array $item) use ($wallIds): bool {
            return isset($item['wall_id']) && isset($wallIds[(string) $item['wall_id']]);
        };

        $doors = [];
        foreach ((array) ($payload['doors'] ?? []) as $d) {
            if (! is_array($d) || ! $anchored($d)) {
                continue;
            }
            $doors[] = [
                'id' => (string) ($d['id'] ?? uniqid('d', true)),
                'wall_id' => (string) $d['wall_id'],
                't' => max(0.0, min(1.0, (float) ($d['t'] ?? 0.5))),
                'width_m' => max(0.4, min(3.0, (float) ($d['width_m'] ?? 0.9))),
                'swing' => in_array($d['swing'] ?? '', ['left_in', 'left_out', 'right_in', 'right_out'], true)
                    ? (string) $d['swing']
                    : 'left_in',
            ];
        }

        $windows = [];
        foreach ((array) ($payload['windows'] ?? []) as $w) {
            if (! is_array($w) || ! $anchored($w)) {
                continue;
            }
            $windows[] = [
                'id' => (string) ($w['id'] ?? uniqid('win', true)),
                'wall_id' => (string) $w['wall_id'],
                't' => max(0.0, min(1.0, (float) ($w['t'] ?? 0.5))),
                'width_m' => max(0.2, min(5.0, (float) ($w['width_m'] ?? 1.2))),
            ];
        }

        $labels = [];
        foreach ((array) ($payload['labels'] ?? []) as $l) {
            if (! is_array($l) || ! isset($l['pos']) || ! is_array($l['pos']) || count($l['pos']) !== 2) {
                continue;
            }
            $text = trim((string) ($l['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $labels[] = [
                'id' => (string) ($l['id'] ?? uniqid('l', true)),
                'pos' => [(float) $l['pos'][0], (float) $l['pos'][1]],
                'text' => mb_substr($text, 0, 120),
            ];
        }

        return [
            'version' => 1,
            'walls' => $walls,
            'doors' => $doors,
            'windows' => $windows,
            'labels' => $labels,
        ];
    }

    /**
     * Read-only overlay markers shown on top of the editor canvas: racks and
     * unracked equipment positioned in this room, plus their resolved icon
     * URLs (per-record → tenant → global). The user toggles their visibility
     * client-side; they're never edited from the plan editor.
     *
     * @return array{
     *   racks: Collection<int, Rack>,
     *   equipment: Collection<int, Equipment>,
     *   rackIcons: array<int, ?string>,
     *   equipmentIcons: array<int, ?string>,
     * }
     */
    private function overlayMarkers(IconResolver $resolver): array
    {
        $racks = Rack::query()
            ->where('room_id', $this->room->getKey())
            ->whereNotNull('position_x')
            ->whereNotNull('position_y')
            ->orderBy('name')
            ->get(['id', 'name', 'position_x', 'position_y', 'icon_path', 'icon_size_px']);

        $equipment = Equipment::query()
            ->whereNull('rack_id')
            ->where('room_id', $this->room->getKey())
            ->whereNotNull('position_x')
            ->whereNotNull('position_y')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'position_x', 'position_y', 'icon_path', 'icon_size_px']);

        $tenantId = (int) ($this->room->tenant_id ?? 0);
        $roomDefaultSize = $this->room->rack_icon_size_px !== null
            ? (int) $this->room->rack_icon_size_px
            : RoomMap::DEFAULT_ICON_SIZE_PX;

        $rackIcons = [];
        $rackIconSizes = [];
        foreach ($racks as $r) {
            $rackIcons[$r->id] = $resolver->urlForRack($r, $tenantId);
            $rackIconSizes[$r->id] = $r->icon_size_px !== null ? (int) $r->icon_size_px : $roomDefaultSize;
        }
        $equipmentIcons = [];
        $equipmentIconSizes = [];
        foreach ($equipment as $eq) {
            $equipmentIcons[$eq->id] = $resolver->urlForEquipment($eq, $tenantId);
            $equipmentIconSizes[$eq->id] = $eq->icon_size_px !== null ? (int) $eq->icon_size_px : $roomDefaultSize;
        }

        return compact('racks', 'equipment', 'rackIcons', 'equipmentIcons', 'rackIconSizes', 'equipmentIconSizes');
    }

    public function render(IconResolver $resolver): View
    {
        return view('livewire.rooms.plan-editor', $this->overlayMarkers($resolver));
    }
}
