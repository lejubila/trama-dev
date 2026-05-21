@props([
    'rack',
    'interactive' => true,
    'orient' => 'front', // 'front' | 'rear'
])

@php
    use App\Enums\RackNumbering;

    $uPx        = 24;
    $padLeft    = 60;
    $rackInner  = 530;
    $svgWidth   = 600;
    $onTopH     = 28; // reserved strip above the rack frame for on-top devices
    $padTop     = 20 + $onTopH;
    $padBottom  = 20;
    $svgHeight  = $padTop + $padBottom + ($rack->height_units * $uPx);

    /** Map U position → screen Y. We always render with U1 at the bottom of
     *  the SVG; the `numbering` flag only changes how labels are computed. */
    $yForU = fn (int $u): float => $padTop + ($rack->height_units - $u) * $uPx;

    /** Numbering label for U at $u (1-based, physical bottom). */
    $labelForU = fn (int $u): int => $rack->numbering === RackNumbering::TopDown
        ? $rack->height_units - $u + 1
        : $u;

    $equipment = $rack->equipment()
        ->with('interfaces')
        ->where('mounted', true)
        ->where(function ($qq): void {
            $qq->where('on_top', false)->orWhereNull('on_top');
        })
        ->whereNotNull('position_u_start')
        ->whereNotNull('position_u_height')
        ->when($orient === 'rear',
            fn ($q) => $q->where('position_orient', 'rear'),
            fn ($q) => $q->where(function ($qq) {
                $qq->whereNull('position_orient')->orWhere('position_orient', 'front');
            }),
        )
        ->get();

    // Devices placed ON TOP of the rack: visible from both front and rear,
    // rendered as small horizontal tiles in the strip above the frame.
    $onTopEquipment = $rack->equipment()
        ->where('mounted', true)
        ->where('on_top', true)
        ->orderBy('name')
        ->get();

    $occupied = [];      // u => true if any device covers it
    $overlapAtU = [];    // u => number of devices covering it
    foreach ($equipment as $eq) {
        for ($u = $eq->position_u_start; $u < $eq->position_u_start + $eq->position_u_height; $u++) {
            $occupied[$u] = true;
            $overlapAtU[$u] = ($overlapAtU[$u] ?? 0) + 1;
        }
    }

    // Multi-device-per-U: assign each equipment a horizontal "lane" so
    // overlapping items render side-by-side instead of on top of each other.
    // Greedy interval-coloring on the U range; lane 0 is leftmost.
    $sortedEq = $equipment->sortBy('position_u_start')->values();
    $lanes = [];           // eqId => lane index
    $laneRanges = [];      // lane index => list of [start,end] U ranges already taken
    foreach ($sortedEq as $eq) {
        $s = (int) $eq->position_u_start;
        $e = $s + (int) $eq->position_u_height - 1;
        $lane = 0;
        while (true) {
            $clash = false;
            foreach ($laneRanges[$lane] ?? [] as [$os, $oe]) {
                if ($s <= $oe && $os <= $e) { $clash = true; break; }
            }
            if (! $clash) break;
            $lane++;
        }
        $lanes[$eq->id] = $lane;
        $laneRanges[$lane][] = [$s, $e];
    }

    // Per-device "local max overlap": the densest U the device covers. A
    // device alone in its range gets localMax=1 → renders full-width; a
    // device sharing any U with N-1 others gets localMax=N → renders at
    // 1/N of the area. Rows with only one device stay full-width even if
    // other rows of the rack are crowded.
    $localMax = [];
    foreach ($sortedEq as $eq) {
        $s = (int) $eq->position_u_start;
        $e = $s + (int) $eq->position_u_height - 1;
        $m = 1;
        for ($u = $s; $u <= $e; $u++) {
            $m = max($m, $overlapAtU[$u] ?? 1);
        }
        $localMax[$eq->id] = $m;
    }

    // Reserve a slim column on the right for the per-row "+" add button.
    $addBtnW = 18;
    $devicesArea = max(40, $rackInner - ($interactive ? $addBtnW + 4 : 0));

    /** Tailwind palette → hex (light/dark pair) for the equipment coloring. */
    $palette = [
        'cyan'    => ['bg' => '#cffafe', 'fg' => '#0891b2'],
        'violet'  => ['bg' => '#ede9fe', 'fg' => '#7c3aed'],
        'red'     => ['bg' => '#fee2e2', 'fg' => '#dc2626'],
        'emerald' => ['bg' => '#d1fae5', 'fg' => '#059669'],
        'amber'   => ['bg' => '#fef3c7', 'fg' => '#d97706'],
        'slate'   => ['bg' => '#f1f5f9', 'fg' => '#64748b'],
        'blue'    => ['bg' => '#dbeafe', 'fg' => '#2563eb'],
        'yellow'  => ['bg' => '#fef9c3', 'fg' => '#ca8a04'],
        'fuchsia' => ['bg' => '#fae8ff', 'fg' => '#c026d3'],
        'teal'    => ['bg' => '#ccfbf1', 'fg' => '#0d9488'],
        'orange'  => ['bg' => '#ffedd5', 'fg' => '#ea580c'],
        'indigo'  => ['bg' => '#e0e7ff', 'fg' => '#4f46e5'],
        'lime'    => ['bg' => '#ecfccb', 'fg' => '#65a30d'],
        'sky'     => ['bg' => '#e0f2fe', 'fg' => '#0284c7'],
        'pink'    => ['bg' => '#fce7f3', 'fg' => '#db2777'],
        'rose'    => ['bg' => '#ffe4e6', 'fg' => '#e11d48'],
        'gray'    => ['bg' => '#f3f4f6', 'fg' => '#6b7280'],
    ];
@endphp

<svg
    {{ $attributes->merge([
        'viewBox' => "0 0 {$svgWidth} {$svgHeight}",
        'class'   => 'rack-elevation w-full max-w-[640px] select-none',
        'data-rack-id' => $rack->id,
        'data-u-px' => $uPx,
        'data-pad-top' => $padTop,
        'data-rack-units' => $rack->height_units,
    ]) }}
    xmlns="http://www.w3.org/2000/svg"
>
    {{-- On-top strip: devices placed ON TOP of the rack frame, side by side.
         Visible from both front and rear views; the same set is rendered
         regardless of `orient`. The "+" button on the right always allows
         adding another on-top device (multiple are supported). --}}
    @php
        $stripY = $padTop - $onTopH - 2;
        $addBtnW = 22; // SVG units reserved on the right for the "+" button
        $stripCount = $onTopEquipment->count();
        $itemsArea = $rackInner - ($interactive ? $addBtnW + 6 : 0);
        $itemW = $stripCount > 0 ? max(60, ($itemsArea / $stripCount) - 4) : 0;
    @endphp

    @if ($onTopEquipment->isNotEmpty() || $interactive)
        <text x="{{ $padLeft }}" y="{{ $stripY - 4 }}" font-size="9" fill="#6b7280">Sopra il rack</text>
        <rect
            x="{{ $padLeft - 5 }}" y="{{ $stripY }}"
            width="{{ $rackInner + 10 }}" height="{{ $onTopH }}"
            fill="#f9fafb" stroke="#9ca3af" stroke-width="1" stroke-dasharray="2 3"
        />
        @foreach ($onTopEquipment as $i => $eq)
            @php
                $color = $palette[$eq->type?->color() ?? 'gray'] ?? $palette['gray'];
                $ix = $padLeft + 4 + $i * ($itemW + 4);
            @endphp
            <g class="rack-equipment cursor-pointer" data-id="{{ $eq->id }}" data-locked="1"
                wire:click="$dispatch('equipment-clicked', { id: {{ $eq->id }} })">
                <rect
                    x="{{ $ix }}" y="{{ $stripY + 3 }}"
                    width="{{ $itemW }}" height="{{ $onTopH - 6 }}"
                    fill="{{ $color['bg'] }}" stroke="{{ $color['fg'] }}" stroke-width="1.2" rx="2"
                />
                <text
                    class="pointer-events-none"
                    x="{{ $ix + 6 }}" y="{{ $stripY + $onTopH / 2 + 3 }}"
                    font-size="11" font-weight="600" fill="#111827"
                >{{ $eq->name }}</text>
            </g>
        @endforeach

        @if ($interactive)
            @php $btnX = $padLeft + $rackInner - $addBtnW - 2; @endphp
            <g class="rack-ontop-add cursor-pointer"
                wire:click="$dispatch('on-top-clicked')">
                <title>Aggiungi dispositivo sopra il rack</title>
                <rect
                    x="{{ $btnX }}" y="{{ $stripY + 3 }}"
                    width="{{ $addBtnW }}" height="{{ $onTopH - 6 }}"
                    fill="#eef2ff" stroke="#6366f1" stroke-width="1.2" rx="3"
                />
                <text
                    class="pointer-events-none"
                    x="{{ $btnX + $addBtnW / 2 }}" y="{{ $stripY + $onTopH / 2 + 5 }}"
                    text-anchor="middle"
                    font-size="16" font-weight="700" fill="#4f46e5"
                >+</text>
            </g>
        @endif
    @endif

    {{-- Rack frame --}}
    <rect
        x="{{ $padLeft - 5 }}" y="{{ $padTop }}"
        width="{{ $rackInner + 10 }}" height="{{ $rack->height_units * $uPx }}"
        fill="#ffffff" stroke="#374151" stroke-width="2"
    />

    {{-- U numbering + slot click handlers --}}
    @for ($u = 1; $u <= $rack->height_units; $u++)
        @php $y = $yForU($u); @endphp
        <text
            x="{{ $padLeft - 12 }}" y="{{ $y + 16 }}"
            text-anchor="end"
            font-size="11" fill="#6b7280" font-family="ui-monospace, monospace"
        >{{ $labelForU($u) }}</text>

        @if ($interactive && empty($occupied[$u]))
            <rect
                class="rack-slot cursor-pointer"
                x="{{ $padLeft }}" y="{{ $y }}"
                width="{{ $devicesArea }}" height="{{ $uPx }}"
                fill="transparent"
                stroke="#e5e7eb" stroke-width="1" stroke-dasharray="2 4"
                data-u="{{ $u }}"
                wire:click="$dispatch('slot-clicked', { u: {{ $u }}, orient: '{{ $orient }}' })"
            />
            <text
                class="pointer-events-none"
                x="{{ $padLeft + $devicesArea / 2 }}" y="{{ $y + $uPx / 2 + 4 }}"
                text-anchor="middle"
                font-size="11" fill="#9ca3af"
            >+</text>
        @endif

        {{-- Per-row "+" only when the unit already has at least one device,
             to add another one (multi-device per U). Empty units are
             served by the dashed full-row click area above. --}}
        @if ($interactive && ! empty($occupied[$u]))
            <g class="rack-slot-add cursor-pointer"
                wire:click="$dispatch('slot-clicked', { u: {{ $u }}, orient: '{{ $orient }}' })">
                <title>Aggiungi dispositivo in U{{ $labelForU($u) }}</title>
                <rect
                    x="{{ $padLeft + $devicesArea + 2 }}" y="{{ $y + 2 }}"
                    width="{{ $addBtnW - 4 }}" height="{{ $uPx - 4 }}"
                    rx="2" fill="#eef2ff" stroke="#6366f1" stroke-width="1"
                />
                <text
                    class="pointer-events-none"
                    x="{{ $padLeft + $devicesArea + 2 + ($addBtnW - 4) / 2 }}"
                    y="{{ $y + $uPx / 2 + 5 }}"
                    text-anchor="middle"
                    font-size="14" font-weight="700" fill="#4f46e5"
                >+</text>
            </g>
        @endif
    @endfor

    {{-- Equipment rectangles (lane-aware: overlapping U get rendered
         side-by-side instead of stacking). --}}
    @foreach ($equipment as $eq)
        @php
            $startY = $yForU($eq->position_u_start + $eq->position_u_height - 1);
            $h      = $eq->position_u_height * $uPx;
            $color  = $palette[$eq->type?->color() ?? 'gray'] ?? $palette['gray'];
            $lane   = $lanes[$eq->id] ?? 0;
            $max    = $localMax[$eq->id] ?? 1;
            $w      = $devicesArea / $max;
            $x      = $padLeft + $lane * $w;
        @endphp
        <g
            class="rack-equipment cursor-pointer"
            data-id="{{ $eq->id }}"
            data-locked="{{ $eq->locked ? '1' : '0' }}"
            data-u-start="{{ $eq->position_u_start }}"
            data-u-height="{{ $eq->position_u_height }}"
            wire:click="$dispatch('equipment-clicked', { id: {{ $eq->id }} })"
        >
            <rect
                x="{{ $x }}" y="{{ $startY }}"
                width="{{ $w }}" height="{{ $h }}"
                fill="{{ $color['bg'] }}" stroke="{{ $color['fg'] }}" stroke-width="1.5"
                rx="2"
            />
            @if ($eq->locked)
                <text
                    class="pointer-events-none"
                    x="{{ $x + $w - 12 }}" y="{{ $startY + 14 }}"
                    text-anchor="end" font-size="10" fill="{{ $color['fg'] }}"
                >🔒</text>
            @endif
            <text
                class="pointer-events-none"
                x="{{ $x + 6 }}" y="{{ $startY + 16 }}"
                font-size="12" font-weight="600" fill="#111827"
            >{{ $eq->name }}</text>
            @if ($h >= 36)
                <text
                    class="pointer-events-none"
                    x="{{ $x + 6 }}" y="{{ $startY + 32 }}"
                    font-size="10" fill="#6b7280"
                >{{ trim(($eq->vendor ?? '').' '.($eq->model ?? '')) }}</text>
            @endif
            {{-- mini interface dots, max 12 in lane width, bottom-right --}}
            @foreach ($eq->interfaces->take(12) as $i => $if)
                <circle
                    class="pointer-events-none"
                    cx="{{ $x + $w - 8 - ($i * 8) }}"
                    cy="{{ $startY + $h - 8 }}"
                    r="2.5"
                    fill="{{ $if->status?->value === 'up' ? '#16a34a' : '#9ca3af' }}"
                />
            @endforeach
        </g>
    @endforeach
</svg>
