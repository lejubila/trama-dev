<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Rack {{ $rack->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body { font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; color: #111827; margin: 0; }
        h1 { font-size: 18pt; margin: 0 0 4pt; }
        .meta { color: #6b7280; font-size: 9pt; margin-bottom: 12pt; }
        .specs { display: flex; gap: 24pt; font-size: 9pt; color: #374151; margin-bottom: 16pt; }
        .specs dt { font-weight: 600; }
        .specs dd { margin: 0; }
        .footer { font-size: 8pt; color: #9ca3af; margin-top: 16pt; }
        svg { width: 100%; max-width: none; }
    </style>
</head>
<body>
    <h1>Rack {{ $rack->name }}</h1>
    <div class="meta">{{ $rack->room->name }} — {{ $rack->room->site->name }} · esportato {{ now()->format('Y-m-d H:i') }}</div>

    <div class="specs">
        <div><dt>Altezza</dt><dd>{{ $rack->height_units }} U</dd></div>
        <div><dt>Larghezza</dt><dd>{{ $rack->width_mm ?? '—' }} mm</dd></div>
        <div><dt>Profondità</dt><dd>{{ $rack->depth_mm ?? '—' }} mm</dd></div>
        <div><dt>Numerazione</dt><dd>{{ $rack->numbering->value }}</dd></div>
    </div>

    <x-rack-elevation :rack="$rack" :interactive="false" />

    @if ($rack->notes)
        <div class="footer">Note: {{ $rack->notes }}</div>
    @endif
</body>
</html>
