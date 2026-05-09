@props(['room'])

@php
    $racks = $room->racks()->get(['id', 'name', 'position_x', 'position_y', 'width_mm', 'depth_mm']);
    $hasCoords = $racks->contains(fn ($r) => $r->position_x !== null && $r->position_y !== null);

    // Auto-fit: pad+normalize coordinates to a 600×400 viewBox.
    $padding = 20;
    $vbW = 600;
    $vbH = 400;
    $minX = $maxX = $minY = $maxY = null;
    if ($hasCoords) {
        foreach ($racks as $r) {
            if ($r->position_x !== null && $r->position_y !== null) {
                $minX = $minX === null ? (float) $r->position_x : min($minX, (float) $r->position_x);
                $maxX = $maxX === null ? (float) $r->position_x : max($maxX, (float) $r->position_x);
                $minY = $minY === null ? (float) $r->position_y : min($minY, (float) $r->position_y);
                $maxY = $maxY === null ? (float) $r->position_y : max($maxY, (float) $r->position_y);
            }
        }
        $rangeX = max(1, $maxX - $minX);
        $rangeY = max(1, $maxY - $minY);
    }

    $rackPx = 40; // approx rack rendered side
@endphp

@if (! $hasCoords)
    <div class="text-sm text-gray-500 dark:text-slate-400 italic">
        Nessun rack ha coordinate sulla planimetria. Imposta `position_x`/`position_y` sul rack per disegnarlo qui.
    </div>
@else
    <svg
        viewBox="0 0 {{ $vbW }} {{ $vbH }}"
        class="w-full max-w-[640px] bg-gray-50 dark:bg-slate-900 rounded-md border border-gray-200 dark:border-slate-700"
        xmlns="http://www.w3.org/2000/svg"
    >
        @foreach ($racks as $r)
            @if ($r->position_x !== null && $r->position_y !== null)
                @php
                    $x = $padding + (((float) $r->position_x - $minX) / $rangeX) * ($vbW - $padding * 2 - $rackPx);
                    $y = $padding + (((float) $r->position_y - $minY) / $rangeY) * ($vbH - $padding * 2 - $rackPx);
                @endphp
                <a href="{{ route('racks.show', $r) }}">
                    <rect
                        x="{{ $x }}" y="{{ $y }}"
                        width="{{ $rackPx }}" height="{{ $rackPx }}"
                        fill="#e0e7ff" stroke="#4f46e5" stroke-width="1.5" rx="3"
                        class="hover:fill-indigo-200"
                    />
                    <text
                        x="{{ $x + $rackPx / 2 }}" y="{{ $y + $rackPx / 2 + 4 }}"
                        text-anchor="middle"
                        font-size="11" fill="#1f2937" font-weight="600"
                    >{{ $r->name }}</text>
                </a>
            @endif
        @endforeach
    </svg>
@endif
