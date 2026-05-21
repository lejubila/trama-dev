@php
    $scale = \App\Livewire\Rooms\Map::SCALE;
    $rackPx = \App\Livewire\Rooms\Map::RACK_PX;
    $halfRackPx = $rackPx / 2;
    $vbW = $widthM * $scale;
    $vbH = $depthM * $scale;
    // Root-relative URL on purpose: Storage::url() prepends APP_URL, which
    // breaks when the app is reached from a host other than the one in .env.
    $floorPlanUrl = $room->floor_plan_path ? '/storage/'.ltrim($room->floor_plan_path, '/') : null;
@endphp
<div x-data="roomMapDnD" x-init="init($el)">
    @if ($racks->isEmpty())
        <div class="text-sm text-gray-500 dark:text-slate-400 italic">
            {{ __('rooms.map_empty') }}
        </div>
    @else
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
                    class="room-map-rack"
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
