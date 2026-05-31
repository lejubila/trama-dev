@php
    $scale = \App\Livewire\Rooms\Map::SCALE;
    $rackPx = \App\Livewire\Rooms\Map::RACK_PX;
    $halfRackPx = $rackPx / 2;
    $vbW = $widthM * $scale;
    $vbH = $depthM * $scale;
    // Root-relative URL on purpose: Storage::url() prepends APP_URL, which
    // breaks when the app is reached from a host other than the one in .env.
    $floorPlanUrl = $room->floor_plan_path ? '/storage/'.ltrim($room->floor_plan_path, '/') : null;
    $drawing = is_array($room->floor_plan_drawing) ? $room->floor_plan_drawing : null;
    $wallsById = [];
    foreach ($drawing['walls'] ?? [] as $w) {
        $wallsById[$w['id']] = $w;
    }
    $wallParam = function (array $wall, float $t): array {
        $pts = $wall['points'] ?? [];
        if (count($pts) < 2) return ['x' => 0.0, 'y' => 0.0, 'seg' => 0];
        $total = 0.0;
        $segLens = [];
        for ($i = 0; $i < count($pts) - 1; $i++) {
            $d = hypot($pts[$i+1][0] - $pts[$i][0], $pts[$i+1][1] - $pts[$i][1]);
            $segLens[] = $d;
            $total += $d;
        }
        if ($total === 0.0) return ['x' => (float) $pts[0][0], 'y' => (float) $pts[0][1], 'seg' => 0];
        $target = max(0.0, min(1.0, $t)) * $total;
        for ($i = 0; $i < count($segLens); $i++) {
            if ($target <= $segLens[$i] || $i === count($segLens) - 1) {
                $u = $segLens[$i] === 0.0 ? 0.0 : $target / $segLens[$i];
                return [
                    'x' => $pts[$i][0] + ($pts[$i+1][0] - $pts[$i][0]) * $u,
                    'y' => $pts[$i][1] + ($pts[$i+1][1] - $pts[$i][1]) * $u,
                    'seg' => $i,
                ];
            }
            $target -= $segLens[$i];
        }
        return ['x' => (float) $pts[0][0], 'y' => (float) $pts[0][1], 'seg' => 0];
    };
    $wallAngleDeg = function (array $wall, int $seg): float {
        $pts = $wall['points'] ?? [];
        if (! isset($pts[$seg], $pts[$seg + 1])) return 0.0;
        return rad2deg(atan2($pts[$seg + 1][1] - $pts[$seg][1], $pts[$seg + 1][0] - $pts[$seg][0]));
    };
@endphp
<div x-data="roomMapDnD" x-init="init($el)">
    @php
        $hasFloorPlan = $floorPlanUrl !== null || $drawing !== null;
        $hasItems = $racks->isNotEmpty() || $equipments->isNotEmpty();
    @endphp
    @if (! $hasItems && ! $hasFloorPlan)
        <div class="text-sm text-gray-500 dark:text-slate-400 italic">
            {{ __('rooms.map_empty') }}
        </div>
    @else
        @if (! $hasItems)
            <div class="mb-2 text-xs text-gray-500 dark:text-slate-400 italic">
                {{ __('rooms.map_empty') }}
            </div>
        @endif
        <svg
            wire:ignore
            class="room-map w-full bg-gray-50 dark:bg-slate-900 rounded-md border border-gray-200 dark:border-slate-700 select-none"
            viewBox="0 0 {{ $vbW }} {{ $vbH }}"
            preserveAspectRatio="xMidYMid meet"
            data-scale="{{ $scale }}"
            data-room-w-m="{{ $widthM }}"
            data-room-h-m="{{ $depthM }}"
            data-room-icon-size-px="{{ $roomIconSize }}"
            data-can-edit="{{ $canEdit ? '1' : '0' }}"
            data-min-icon-px="{{ \App\Livewire\Rooms\Map::MIN_ICON_SIZE_PX }}"
            data-max-icon-px="{{ \App\Livewire\Rooms\Map::MAX_ICON_SIZE_PX }}"
            xmlns="http://www.w3.org/2000/svg"
            style="touch-action: none;"
        >
            @if ($floorPlanUrl)
                <image href="{{ $floorPlanUrl }}" x="0" y="0" width="{{ $vbW }}" height="{{ $vbH }}" preserveAspectRatio="none" opacity="0.85" />
            @endif

            @if ($drawing)
                <g class="floor-plan" style="pointer-events: none;">
                    @foreach ($drawing['walls'] ?? [] as $w)
                        @php
                            $pointsAttr = implode(' ', array_map(fn ($p) => ($p[0] * $scale).','.($p[1] * $scale), $w['points']));
                            $strokeW = (float) ($w['thickness_m'] ?? 0.15) * $scale;
                        @endphp
                        <polyline points="{{ $pointsAttr }}" fill="none" stroke="#0f172a" stroke-width="{{ $strokeW }}" stroke-linecap="square" stroke-linejoin="miter" />
                    @endforeach
                    @foreach ($drawing['windows'] ?? [] as $wn)
                        @php
                            $wall = $wallsById[$wn['wall_id']] ?? null;
                            if (! $wall) continue;
                            $p = $wallParam($wall, (float) $wn['t']);
                            $cxp = $p['x'] * $scale;
                            $cyp = $p['y'] * $scale;
                            $angle = $wallAngleDeg($wall, $p['seg']);
                            $wpx = (float) ($wn['width_m'] ?? 1.2) * $scale;
                        @endphp
                        <g transform="translate({{ $cxp }},{{ $cyp }}) rotate({{ $angle }})">
                            <rect x="{{ -$wpx / 2 }}" y="-3" width="{{ $wpx }}" height="6" fill="#bfdbfe" stroke="#2563eb" stroke-width="1" />
                        </g>
                    @endforeach
                    @foreach ($drawing['doors'] ?? [] as $d)
                        @php
                            $wall = $wallsById[$d['wall_id']] ?? null;
                            if (! $wall) continue;
                            $p = $wallParam($wall, (float) $d['t']);
                            $cxp = $p['x'] * $scale;
                            $cyp = $p['y'] * $scale;
                            $angle = $wallAngleDeg($wall, $p['seg']);
                            $wpx = (float) ($d['width_m'] ?? 0.9) * $scale;
                            $swing = $d['swing'] ?? 'left_in';
                            $sweepDir = str_starts_with($swing, 'left') ? -1 : 1;
                            $inOut = str_ends_with($swing, 'in') ? 1 : -1;
                        @endphp
                        @php
                            $hingeX = -$wpx / 2 * $sweepDir;
                            $jambX = $wpx / 2 * $sweepDir;
                            $leafEndY = $wpx * $inOut;
                            $arcFlag = $sweepDir === $inOut ? 0 : 1;
                        @endphp
                        <g transform="translate({{ $cxp }},{{ $cyp }}) rotate({{ $angle }})">
                            <line x1="{{ $hingeX }}" y1="0" x2="{{ $jambX }}" y2="0" stroke="#ffffff" stroke-width="2.2" />
                            <line x1="{{ $hingeX }}" y1="0" x2="{{ $hingeX }}" y2="{{ $leafEndY }}" stroke="#dc2626" stroke-width="1.2" stroke-linecap="round" />
                            <path d="M {{ $hingeX }} {{ $leafEndY }} A {{ $wpx }} {{ $wpx }} 0 0 {{ $arcFlag }} {{ $jambX }} 0" fill="none" stroke="#dc2626" stroke-width="0.6" />
                        </g>
                    @endforeach
                    @foreach ($drawing['labels'] ?? [] as $l)
                        <text x="{{ $l['pos'][0] * $scale }}" y="{{ $l['pos'][1] * $scale }}" text-anchor="middle" font-size="11" font-weight="600" fill="#111827">{{ $l['text'] }}</text>
                    @endforeach
                </g>
            @endif

            @for ($i = 1; $i * $scale < $vbW; $i++)
                <line x1="{{ $i * $scale }}" y1="0" x2="{{ $i * $scale }}" y2="{{ $vbH }}" stroke="{{ $floorPlanUrl ? '#9ca3af' : '#e5e7eb' }}" stroke-width="0.5" stroke-dasharray="{{ $floorPlanUrl ? '2,2' : '0' }}" />
            @endfor
            @for ($i = 1; $i * $scale < $vbH; $i++)
                <line x1="0" y1="{{ $i * $scale }}" x2="{{ $vbW }}" y2="{{ $i * $scale }}" stroke="{{ $floorPlanUrl ? '#9ca3af' : '#e5e7eb' }}" stroke-width="0.5" stroke-dasharray="{{ $floorPlanUrl ? '2,2' : '0' }}" />
            @endfor

            @foreach ($racks as $r)
                @php
                    $px = (float) ($r->position_x ?? 0);
                    $py = (float) ($r->position_y ?? 0);
                    $iconUrl = $rackIcons[$r->id] ?? null;
                    $iconSizePx = $rackIconSizes[$r->id] ?? \App\Livewire\Rooms\Map::DEFAULT_ICON_SIZE_PX;
                @endphp
                <g
                    class="room-map-node"
                    data-kind="rack"
                    data-node-id="{{ $r->id }}"
                    data-rack-id="{{ $r->id }}"
                    data-x="{{ $px }}"
                    data-y="{{ $py }}"
                    data-icon-size-px="{{ $iconSizePx }}"
                    data-icon-override="{{ ($rackHasOverride[$r->id] ?? false) ? '1' : '0' }}"
                    data-icon-url="{{ $iconUrl ?? '' }}"
                    data-href="{{ route('racks.show', $r) }}"
                    transform="translate({{ $px * $scale }},{{ $py * $scale }})"
                    style="cursor: {{ $canEdit ? 'grab' : 'pointer' }};"
                >
                    {{-- Children are centered on (0,0); the <g> transform places the CENTER at (px*scale, py*scale).
                         Initial size is approximate; JS replaces it using the SVG's CTM. --}}
                    @if ($iconUrl)
                        <image class="rack-icon" href="{{ $iconUrl }}" x="{{ -$halfRackPx }}" y="{{ -$halfRackPx }}" width="{{ $rackPx }}" height="{{ $rackPx }}" preserveAspectRatio="xMidYMid meet" />
                    @else
                        <rect class="rack-icon"
                            x="{{ -$halfRackPx }}" y="{{ -$halfRackPx }}"
                            width="{{ $rackPx }}" height="{{ $rackPx }}"
                            fill="#e0e7ff" stroke="#4f46e5" stroke-width="1.5" rx="3"
                            fill-opacity="0.9"
                        />
                    @endif
                    <text
                        class="rack-label"
                        x="0" y="{{ $halfRackPx + $rackPx * 0.10 + $rackPx * 0.22 }}"
                        text-anchor="middle"
                        font-size="{{ $rackPx * 0.22 }}" fill="#1f2937" font-weight="600"
                        stroke="#ffffff" stroke-width="{{ $rackPx * 0.22 * 0.25 }}" paint-order="stroke"
                        style="pointer-events: none;"
                    >{{ $r->name }}</text>
                    @if ($canEdit)
                        <rect class="rack-icon-resize"
                            x="{{ $halfRackPx - 4 }}" y="{{ $halfRackPx - 4 }}"
                            width="8" height="8" rx="1.5"
                            fill="#4f46e5" stroke="#ffffff" stroke-width="1"
                            style="cursor: nwse-resize;"
                        />
                        {{-- Reset-to-room-default button: rendered only when
                             this rack has its own size override. JS hides/shows
                             it dynamically as resizes happen. --}}
                        <g class="rack-icon-reset" style="cursor: pointer; display: {{ ($rackHasOverride[$r->id] ?? false) ? 'inline' : 'none' }};">
                            <circle cx="{{ $halfRackPx - 4 }}" cy="{{ -$halfRackPx + 4 }}" r="5" fill="#dc2626" stroke="#ffffff" stroke-width="1" />
                            <text x="{{ $halfRackPx - 4 }}" y="{{ -$halfRackPx + 6 }}" text-anchor="middle" font-size="7" font-weight="700" fill="#ffffff" style="pointer-events: none;">×</text>
                        </g>
                    @endif
                </g>
            @endforeach

            @foreach ($equipments as $eq)
                @php
                    $px = (float) ($eq->position_x ?? 0);
                    $py = (float) ($eq->position_y ?? 0);
                    $iconUrl = $equipmentIcons[$eq->id] ?? null;
                    $iconSizePx = $equipmentIconSizes[$eq->id] ?? \App\Livewire\Rooms\Map::DEFAULT_ICON_SIZE_PX;
                @endphp
                <g
                    class="room-map-node"
                    data-kind="equipment"
                    data-node-id="{{ $eq->id }}"
                    data-x="{{ $px }}"
                    data-y="{{ $py }}"
                    data-icon-size-px="{{ $iconSizePx }}"
                    data-icon-override="{{ ($equipmentHasOverride[$eq->id] ?? false) ? '1' : '0' }}"
                    data-icon-url="{{ $iconUrl ?? '' }}"
                    data-href="{{ route('equipment.show', $eq) }}"
                    transform="translate({{ $px * $scale }},{{ $py * $scale }})"
                    style="cursor: {{ $canEdit ? 'grab' : 'pointer' }};"
                >
                    @if ($iconUrl)
                        <image class="rack-icon" href="{{ $iconUrl }}" x="{{ -$halfRackPx }}" y="{{ -$halfRackPx }}" width="{{ $rackPx }}" height="{{ $rackPx }}" preserveAspectRatio="xMidYMid meet" />
                    @else
                        <rect class="rack-icon"
                            x="{{ -$halfRackPx }}" y="{{ -$halfRackPx }}"
                            width="{{ $rackPx }}" height="{{ $rackPx }}"
                            fill="#fef3c7" stroke="#d97706" stroke-width="1.5" rx="3"
                            fill-opacity="0.9"
                        />
                    @endif
                    <text
                        class="rack-label"
                        x="0" y="{{ $halfRackPx + $rackPx * 0.10 + $rackPx * 0.22 }}"
                        text-anchor="middle"
                        font-size="{{ $rackPx * 0.22 }}" fill="#1f2937" font-weight="600"
                        stroke="#ffffff" stroke-width="{{ $rackPx * 0.22 * 0.25 }}" paint-order="stroke"
                        style="pointer-events: none;"
                    >{{ $eq->name }}</text>
                    @if ($canEdit)
                        <rect class="rack-icon-resize"
                            x="{{ $halfRackPx - 4 }}" y="{{ $halfRackPx - 4 }}"
                            width="8" height="8" rx="1.5"
                            fill="#4f46e5" stroke="#ffffff" stroke-width="1"
                            style="cursor: nwse-resize;"
                        />
                        <g class="rack-icon-reset" style="cursor: pointer; display: {{ ($equipmentHasOverride[$eq->id] ?? false) ? 'inline' : 'none' }};">
                            <circle cx="{{ $halfRackPx - 4 }}" cy="{{ -$halfRackPx + 4 }}" r="5" fill="#dc2626" stroke="#ffffff" stroke-width="1" />
                            <text x="{{ $halfRackPx - 4 }}" y="{{ -$halfRackPx + 6 }}" text-anchor="middle" font-size="7" font-weight="700" fill="#ffffff" style="pointer-events: none;">×</text>
                        </g>
                    @endif
                </g>
            @endforeach
        </svg>
        <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">
            @if ($canEdit)
                {{ __('rooms.map_hint_edit') }}
            @else
                {{ __('rooms.map_hint_view') }}
            @endif
            {{ __('rooms.map_dimensions', ['w' => number_format($widthM, 2), 'h' => number_format($depthM, 2)]) }}@unless ($hasCustomDimensions) {{ __('rooms.map_default_dims') }}@endunless.
        </p>

        @if ($canEdit)
            <div class="flex items-center gap-2 text-xs mt-1" x-data="{ size: {{ $roomIconSize }} }">
                <label class="text-gray-600">{{ __('rooms.slider_label') }}</label>
                <input
                    type="range"
                    min="{{ \App\Livewire\Rooms\Map::MIN_ICON_SIZE_PX }}"
                    max="{{ \App\Livewire\Rooms\Map::MAX_ICON_SIZE_PX }}"
                    step="1"
                    x-model.number="size"
                    @input="$dispatch('room-default-size', { size: parseInt($event.target.value, 10) })"
                    @change="$wire.setRoomIconSize(size)"
                    class="w-40"
                />
                <span class="font-mono text-gray-700" x-text="(size / {{ $scale }}).toFixed(2) + ' m'"></span>
                <button
                    type="button"
                    @click="$dispatch('room-reset-all'); $wire.resetAllRackIcons()"
                    class="ml-2 px-2 py-0.5 rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    title="{{ __('rooms.reset_all_title') }}"
                >{{ __('rooms.reset_all') }}</button>
                <span class="text-gray-500">{{ __('rooms.custom_note') }}</span>
            </div>
        @endif
    @endif
</div>
