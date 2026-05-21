@php
    $numberingLabel = match ($rack->numbering?->value) {
        'top_down' => 'Dall’alto (U1 in alto)',
        'bottom_up' => 'Dal basso (U1 in basso)',
        default => '—',
    };
@endphp

{{-- Page 1 (portrait): specs + front elevation. --}}
<div class="rack-page with-specs">
    <h2>Rack {{ $rack->name }}</h2>
    <p class="muted small">
        {{ $rack->room?->name ?? '—' }}
        @if ($rack->room?->site) / {{ $rack->room->site->name }} @endif
        · {{ $rack->height_units }}U
    </p>

    <table class="data">
        <tbody>
            <tr>
                <th style="width:18%">Altezza</th>
                <td style="width:32%">{{ $rack->height_units }}U</td>
                <th style="width:18%">Numerazione</th>
                <td>{{ $numberingLabel }}</td>
            </tr>
            <tr>
                <th>Larghezza</th>
                <td>{{ $rack->width_mm !== null ? $rack->width_mm.' mm' : '—' }}</td>
                <th>Profondità</th>
                <td>{{ $rack->depth_mm !== null ? $rack->depth_mm.' mm' : '—' }}</td>
            </tr>
            @if (! empty($rack->notes))
                <tr>
                    <th>Note</th>
                    <td colspan="3">{{ $rack->notes }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="elevation">
        <h4>Front</h4>
        <x-rack-elevation :rack="$rack" :interactive="false" orient="front" />
    </div>
</div>

{{-- Page 2 (portrait): rear elevation. --}}
<div class="rack-page">
    <div class="elevation">
        <h4>Rack {{ $rack->name }} — Rear</h4>
        <x-rack-elevation :rack="$rack" :interactive="false" orient="rear" />
    </div>
</div>

{{-- One photo per page; page orientation follows the photo's form factor. --}}
@foreach ($rack->photos as $photo)
    @php
        $path = $photo->photo_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->path($photo->photo_path)
            : null;
        $dataUri = null;
        $landscape = false;
        if ($path && is_file($path)) {
            $bytes = @file_get_contents($path);
            if ($bytes !== false) {
                $mime = function_exists('mime_content_type') ? (@mime_content_type($path) ?: 'image/jpeg') : 'image/jpeg';
                $dataUri = 'data:'.$mime.';base64,'.base64_encode($bytes);
                $size = @getimagesize($path);
                if ($size !== false && $size[1] > 0) {
                    $landscape = $size[0] >= $size[1];
                }
            }
        }
    @endphp
    @if ($dataUri)
        <div class="{{ $landscape ? 'photo-page-l' : 'photo-page-p' }}">
            <h4>Rack {{ $rack->name }} — Foto</h4>
            <img src="{{ $dataUri }}" alt="" />
            @if ($photo->caption)
                <p class="muted small">{{ $photo->caption }}</p>
            @endif
        </div>
    @endif
@endforeach

@if ($equipment->isNotEmpty())
    <div class="device-page">
        @include('exports.document.device-list', [
            'devices' => $equipment,
            'heading' => 'Rack '.$rack->name.' — Dispositivi nel rack',
        ])
    </div>
@endif
