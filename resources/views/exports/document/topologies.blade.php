<section class="section">
    @foreach ($topologies as $t)
        @php
            $snap = $t->snapshot;
            $orient = $t->orientation;
            $imagePath = $snap->image_path
                ? \Illuminate\Support\Facades\Storage::disk('public')->path($snap->image_path)
                : null;
            // Browsershot blocks file:// URLs in HTML for security. Inline the
            // PNG as a base64 data URI so Chromium can fetch it without
            // needing local file access.
            $imageDataUri = null;
            if ($imagePath && is_file($imagePath)) {
                $bytes = @file_get_contents($imagePath);
                if ($bytes !== false) {
                    $imageDataUri = 'data:image/png;base64,'.base64_encode($bytes);
                }
            }

            // Page-break logic: the first topology shares the section's
            // opening page with the "Topologie" title (no extra break).
            // Subsequent ones get a hard break. Orientation just picks the
            // @page rule; for the first item we use the page-only variant
            // so we don't double up the page break.
            $classes = ['topo-block'];
            if ($loop->first) {
                if ($orient === 'landscape') {
                    $classes[] = 'topo-landscape-page';
                }
            } else {
                $classes[] = $orient === 'landscape' ? 'topo-landscape' : 'page-break';
            }
        @endphp
        <div class="{{ implode(' ', $classes) }}">
            @if ($loop->first)
                <h2 id="sec-topologies">Topologie</h2>
                @if ($description)
                    <p class="section-description">{{ $description }}</p>
                @endif
            @endif
            <h3>{{ $snap->title }}</h3>
            <p class="muted small">
                {{ $snap->snapshot_date->format('d/m/Y') }}
                @if ($snap->description) · {{ $snap->description }} @endif
            </p>
            @if ($imageDataUri)
                <div class="topo-frame">
                    <img src="{{ $imageDataUri }}" alt="{{ $snap->title }}" class="topo-image" />
                </div>
            @else
                <p class="muted">Immagine non disponibile.</p>
            @endif
        </div>
    @endforeach
</section>
