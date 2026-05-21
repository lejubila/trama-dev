@php
    $scale  = 50;          // px per meter (matches Livewire room map)
    // Per-rack size comes from the web interface; fall back to the room
    // default, then to the global default (mirrors App\Livewire\Rooms\Map).
    $defaultIconPx = 40;
    $roomDefaultPx = $room->rack_icon_size_px !== null ? (int) $room->rack_icon_size_px : $defaultIconPx;

    $defaultW = 6.0;
    $defaultD = 6.0;
    $widthM = $room->width_m !== null ? (float) $room->width_m : $defaultW;
    $depthM = $room->depth_m !== null ? (float) $room->depth_m : $defaultD;
    $hasCustomDim = $room->width_m !== null && $room->depth_m !== null;

    $vbW = $widthM * $scale;
    $vbH = $depthM * $scale;

    // Inline the floor plan as a data URI; file:// URLs are blocked by
    // Browsershot for security.
    $floorPlanData = null;
    if ($room->floor_plan_path) {
        $abs = \Illuminate\Support\Facades\Storage::disk('public')->path($room->floor_plan_path);
        if (is_file($abs)) {
            $bytes = @file_get_contents($abs);
            if ($bytes !== false) {
                $mime = function_exists('mime_content_type') ? (@mime_content_type($abs) ?: 'image/png') : 'image/png';
                $floorPlanData = 'data:'.$mime.';base64,'.base64_encode($bytes);
            }
        }
    }

    $allRacks = $room->relationLoaded('racks') ? $room->racks : collect();
    $selectedIds = $roomNode->racks->pluck('rack.id')->all();

    // Resolve icons the same way the web room map does: per-rack upload →
    // tenant default → global default. The resolver returns a "/storage/…"
    // URL, which we map back to the public-disk file to inline as a data URI.
    $iconResolver = app(\App\Services\Icons\IconResolver::class);
    $iconTenantId = $room->tenant_id !== null ? (int) $room->tenant_id : null;
@endphp

@if ($allRacks->isNotEmpty() || $floorPlanData)
    <h4>Planimetria</h4>
    <div class="floorplan">
        <svg viewBox="0 0 {{ $vbW }} {{ $vbH }}" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet">
            <rect x="0" y="0" width="{{ $vbW }}" height="{{ $vbH }}" fill="#f8fafc" stroke="#94a3b8" stroke-width="1" />
            @if ($floorPlanData)
                <image href="{{ $floorPlanData }}" x="0" y="0" width="{{ $vbW }}" height="{{ $vbH }}" preserveAspectRatio="none" opacity="0.85" />
            @endif

            {{-- 1 meter grid --}}
            @for ($i = 1; $i * $scale < $vbW; $i++)
                <line x1="{{ $i * $scale }}" y1="0" x2="{{ $i * $scale }}" y2="{{ $vbH }}" stroke="#cbd5e1" stroke-width="0.5" stroke-dasharray="2,2" />
            @endfor
            @for ($i = 1; $i * $scale < $vbH; $i++)
                <line x1="0" y1="{{ $i * $scale }}" x2="{{ $vbW }}" y2="{{ $i * $scale }}" stroke="#cbd5e1" stroke-width="0.5" stroke-dasharray="2,2" />
            @endfor

            @foreach ($allRacks as $r)
                @php
                    $px = (float) ($r->position_x ?? 0);
                    $py = (float) ($r->position_y ?? 0);
                    $cx = $px * $scale;
                    $cy = $py * $scale;
                    $selected = in_array($r->id, $selectedIds, true);
                    $fill   = $selected ? '#c7d2fe' : '#e2e8f0';
                    $stroke = $selected ? '#4338ca' : '#64748b';

                    $sizePx = $r->icon_size_px !== null ? (int) $r->icon_size_px : $roomDefaultPx;
                    $half = $sizePx / 2;

                    // Inline the resolved rack icon as a data URI (file:// URLs
                    // are blocked by Browsershot). Resolves per-rack upload or
                    // the configured tenant/global default rack icon.
                    $iconData = null;
                    $iconUrl = $iconResolver->urlForRack($r, $iconTenantId);
                    if ($iconUrl) {
                        $iconRel = ltrim(\Illuminate\Support\Str::after($iconUrl, '/storage/'), '/');
                        $iconAbs = \Illuminate\Support\Facades\Storage::disk('public')->path($iconRel);
                        if (is_file($iconAbs)) {
                            $iconBytes = @file_get_contents($iconAbs);
                            if ($iconBytes !== false) {
                                $iconMime = function_exists('mime_content_type') ? (@mime_content_type($iconAbs) ?: 'image/png') : 'image/png';
                                $iconData = 'data:'.$iconMime.';base64,'.base64_encode($iconBytes);
                            }
                        }
                    }
                @endphp
                <g transform="translate({{ $cx }},{{ $cy }})">
                    @if ($iconData)
                        <image href="{{ $iconData }}" x="{{ -$half }}" y="{{ -$half }}" width="{{ $sizePx }}" height="{{ $sizePx }}" preserveAspectRatio="xMidYMid meet" />
                    @else
                        <rect x="{{ -$half }}" y="{{ -$half }}" width="{{ $sizePx }}" height="{{ $sizePx }}"
                              fill="{{ $fill }}" stroke="{{ $stroke }}" stroke-width="1.5" rx="3" />
                    @endif
                    <text x="0" y="{{ $half + 12 }}" text-anchor="middle" font-size="10" fill="#1f2937" font-weight="600">{{ $r->name }}</text>
                </g>
            @endforeach
        </svg>
    </div>
    <p class="muted small">
        Locale {{ number_format($widthM, 2) }}m × {{ number_format($depthM, 2) }}m{{ $hasCustomDim ? '' : ' (dimensioni di default)' }}.
        @if ($selectedIds !== [])
            Rack inclusi nel documento in <span style="color:#4338ca; font-weight:600;">indaco</span>.
        @endif
    </p>
@endif
