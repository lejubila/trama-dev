@props([
    'rack',
    'interactive' => true,
    'orient' => 'front', // 'front' | 'rear'
])

@php
    use App\Enums\RackNumbering;

    $uPx        = 24;
    $padTop     = 20;
    $padBottom  = 20;
    $padLeft    = 60;
    $rackInner  = 530;
    $svgWidth   = 600;
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
        ->whereNotNull('position_u_start')
        ->whereNotNull('position_u_height')
        ->when($orient === 'rear',
            fn ($q) => $q->where('position_orient', 'rear'),
            fn ($q) => $q->where(function ($qq) {
                $qq->whereNull('position_orient')->orWhere('position_orient', 'front');
            }),
        )
        ->get();

    $occupied = [];
    foreach ($equipment as $eq) {
        for ($u = $eq->position_u_start; $u < $eq->position_u_start + $eq->position_u_height; $u++) {
            $occupied[$u] = true;
        }
    }

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
                width="{{ $rackInner }}" height="{{ $uPx }}"
                fill="transparent"
                stroke="#e5e7eb" stroke-width="1" stroke-dasharray="2 4"
                data-u="{{ $u }}"
                wire:click="$dispatch('slot-clicked', { u: {{ $u }} })"
            />
            <text
                class="pointer-events-none"
                x="{{ $padLeft + $rackInner / 2 }}" y="{{ $y + $uPx / 2 + 4 }}"
                text-anchor="middle"
                font-size="11" fill="#9ca3af"
            >+</text>
        @endif
    @endfor

    {{-- Equipment rectangles --}}
    @foreach ($equipment as $eq)
        @php
            $startY = $yForU($eq->position_u_start + $eq->position_u_height - 1);
            $h      = $eq->position_u_height * $uPx;
            $color  = $palette[$eq->type?->color() ?? 'gray'] ?? $palette['gray'];
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
                x="{{ $padLeft }}" y="{{ $startY }}"
                width="{{ $rackInner }}" height="{{ $h }}"
                fill="{{ $color['bg'] }}" stroke="{{ $color['fg'] }}" stroke-width="1.5"
                rx="2"
            />
            @if ($eq->locked)
                <text
                    class="pointer-events-none"
                    x="{{ $padLeft + $rackInner - 12 }}" y="{{ $startY + 14 }}"
                    text-anchor="end" font-size="10" fill="{{ $color['fg'] }}"
                >🔒</text>
            @endif
            <text
                class="pointer-events-none"
                x="{{ $padLeft + 10 }}" y="{{ $startY + 16 }}"
                font-size="12" font-weight="600" fill="#111827"
            >{{ $eq->name }}</text>
            @if ($h >= 36)
                <text
                    class="pointer-events-none"
                    x="{{ $padLeft + 10 }}" y="{{ $startY + 32 }}"
                    font-size="10" fill="#6b7280"
                >{{ trim(($eq->vendor ?? '').' '.($eq->model ?? '')) }}</text>
            @endif
            {{-- mini interface dots, max 24, top-right corner --}}
            @foreach ($eq->interfaces->take(24) as $i => $if)
                <circle
                    class="pointer-events-none"
                    cx="{{ $padLeft + $rackInner - 14 - ($i * 8) }}"
                    cy="{{ $startY + $h - 8 }}"
                    r="2.5"
                    fill="{{ $if->status?->value === 'up' ? '#16a34a' : '#9ca3af' }}"
                />
            @endforeach
        </g>
    @endforeach
</svg>
