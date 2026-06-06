@php
    $numberingLabel = match ($rack->numbering?->value) {
        'top_down' => 'Dall’alto (U1 in alto)',
        'bottom_up' => 'Dal basso (U1 in basso)',
        default => '—',
    };
    $includeRear = (bool) ($options['rack_include_rear'] ?? false);
@endphp

{{-- Front card: specs strip + front elevation, kept on the same page when possible. --}}
<div class="rack-card front">
    <h4 id="sec-rack-{{ $rack->id }}">Rack {{ $rack->name }}</h4>
    @include('exports.document._metastrip', ['items' => [
        ['Locale', ($rack->room?->name ?? '—').($rack->room?->site ? ' / '.$rack->room->site->name : '')],
        ['Altezza', $rack->height_units.'U'],
        ['Numerazione', $numberingLabel],
        ['Larghezza', $rack->width_mm !== null ? $rack->width_mm.' mm' : null],
        ['Profondità', $rack->depth_mm !== null ? $rack->depth_mm.' mm' : null],
    ]])
    @if (! empty($rack->notes))
        <p class="small muted">{{ $rack->notes }}</p>
    @endif

    <div class="elevation">
        <h4>Front</h4>
        <x-rack-elevation :rack="$rack" :interactive="false" orient="front" />
    </div>
</div>

{{-- Rear elevation: opt-in via document option (kept on its own page). --}}
@if ($includeRear)
    <div class="rack-card rear">
        <div class="elevation">
            <h4>Rack {{ $rack->name }} — Rear</h4>
            <x-rack-elevation :rack="$rack" :interactive="false" orient="rear" />
        </div>
    </div>
@endif

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
    @include('exports.document.device-list', [
        'devices' => $equipment,
        'heading' => 'Rack '.$rack->name.' — Dispositivi nel rack',
    ])
@endif
