<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 14mm; }
        @page topo-landscape { size: A4 landscape; margin: 10mm; }
        @page rack-portrait    { size: A4 portrait;  margin: 12mm 14mm; }
        @page photo-landscape  { size: A4 landscape; margin: 10mm; }
        @page photo-portrait   { size: A4 portrait;  margin: 12mm; }

        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; font-family: 'DejaVu Sans', Arial, sans-serif; color: #111827; font-size: 10.5pt; line-height: 1.4; }

        /* ── Heading scale ─────────────────────────────────────────── */
        h1 { font-size: 24pt; margin: 0 0 8pt; color: #0f172a; letter-spacing: -0.2pt; }
        h2 {
            font-size: 15pt;
            margin: 0 0 6pt;
            color: #0f172a;
            padding: 2pt 0 2pt 8pt;
            border-left: 3pt solid #4338ca;
        }
        h3 {
            font-size: 12pt;
            margin: 12pt 0 4pt;
            color: #1e293b;
            padding-bottom: 2pt;
            border-bottom: 0.5pt solid #cbd5e1;
        }
        h4 {
            font-size: 9pt;
            margin: 8pt 0 3pt;
            color: #4338ca;
            text-transform: uppercase;
            letter-spacing: 0.6pt;
            font-weight: 700;
        }
        p  { margin: 0 0 5pt; }
        .muted { color: #64748b; }
        .small { font-size: 8.5pt; }

        /* ── Page-break primitives ─────────────────────────────────── */
        /* Only first-level sections (Sede, Topologie, Wi-Fi, VPN) break. */
        .section { page-break-before: always; }
        .section:first-of-type { page-break-before: avoid; }

        /* Within a section, never break a card mid-flow. */
        .rack-card, .device-block, .meta-strip, .floorplan, .topo-block { break-inside: avoid; page-break-inside: avoid; }

        /* The Rear elevation of a rack still merits its own page when
           opted-in: the elevation is heavy and reads better alone. */
        .rack-card.rear { page-break-before: always; page: rack-portrait; }

        .page-break { page-break-before: always; }
        .topo-landscape { page: topo-landscape; page-break-before: always; }
        .topo-landscape-page { page: topo-landscape; }

        /* ── Cover ─────────────────────────────────────────────────── */
        .cover { text-align: center; padding-top: 55mm; page-break-after: always; }
        .cover h1 { font-size: 32pt; margin-bottom: 14pt; }
        .cover .meta { font-size: 11pt; color: #475569; margin-top: 40pt; }
        .cover .description { max-width: 70%; margin: 24pt auto 0; text-align: left; color: #334155; }

        /* ── TOC ──────────────────────────────────────────────────── */
        .toc { padding-top: 6mm; page-break-after: always; }
        .toc h2 { border-left: 3pt solid #4338ca; }
        .toc-list { list-style: none; padding: 0; margin: 0; }
        .toc-list > li { padding: 4pt 0; border-bottom: 0.5pt dotted #cbd5e1; }
        .toc-list ul { list-style: none; padding-left: 14pt; margin: 2pt 0; }
        .toc-list ul li { padding: 2pt 0; border: none; font-size: 9.5pt; color: #475569; }
        .toc-list ul ul li { padding: 1pt 0; font-size: 9pt; color: #64748b; }
        /* Leader dots + page numbers via CSS (Chromium print supports both). */
        .toc-list a {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: baseline;
            gap: 0;
        }
        .toc-list a .label { white-space: nowrap; }
        .toc-list a .dots {
            flex: 1;
            border-bottom: 0.5pt dotted #cbd5e1;
            margin: 0 4pt;
            transform: translateY(-2pt);
        }
        .toc-list a .pg { color: #64748b; font-variant-numeric: tabular-nums; }

        /* ── Tables ────────────────────────────────────────────────── */
        table.data { width: 100%; border-collapse: collapse; margin-top: 5pt; font-size: 9pt; }
        table.data th, table.data td { border: 0.5pt solid #cbd5e1; padding: 3pt 5pt; text-align: left; vertical-align: top; }
        table.data th { background: #f1f5f9; font-weight: 600; color: #334155; }
        table.data.ports-dense { font-size: 8.5pt; }
        table.data.ports-comfy { font-size: 9pt; }
        table.data.compact { font-size: 8.5pt; }
        table.data.compact th, table.data.compact td { padding: 2.5pt 4pt; }

        /* ── Metadata strip (inline DL) ────────────────────────────── */
        .meta-strip { margin: 0 0 6pt; padding: 0; font-size: 9pt; color: #334155; }
        .meta-strip > div { display: inline; margin-right: 8pt; }
        .meta-strip > div + div::before { content: "·"; color: #cbd5e1; margin-right: 8pt; }
        .meta-strip dt { display: inline; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5pt; color: #64748b; font-weight: 600; margin-right: 4pt; }
        .meta-strip dd { display: inline; margin: 0; font-weight: 500; }

        .section-description { color: #475569; margin: 4pt 0 10pt; font-style: italic; }

        /* ── Topology blocks ──────────────────────────────────────── */
        .topo-image { display: block; max-width: 100%; max-height: 215mm; margin: 6pt auto; }
        .topo-landscape .topo-image,
        .topo-landscape-page .topo-image { max-height: 130mm; }

        /* ── Rack pages (Front always; Rear opt-in) ───────────────── */
        .rack-card { page: rack-portrait; padding-top: 2pt; }
        .rack-card.front { page-break-before: always; }
        .rack-card:first-of-type { page-break-before: avoid; }
        .rack-card .elevation { text-align: center; }
        .rack-card .elevation h4 { margin: 6pt 0 3pt; break-after: avoid; page-break-after: avoid; }
        .rack-card .elevation svg { width: auto; height: auto; max-width: 100%; max-height: 180mm; display: block; margin: 0 auto; }
        .rack-card.front .elevation svg { max-height: 170mm; }

        /* ── Photo pages ──────────────────────────────────────────── */
        .photo-page-l { page: photo-landscape; page-break-before: always; text-align: center; }
        .photo-page-p { page: photo-portrait;  page-break-before: always; text-align: center; }
        .photo-page-l img, .photo-page-p img { max-width: 100%; display: block; margin: 0 auto; object-fit: contain; border: 0.5pt solid #cbd5e1; }
        .photo-page-l img { max-height: 165mm; }
        .photo-page-p img { max-height: 235mm; }

        /* ── Device cards ─────────────────────────────────────────── */
        .device-block { margin-top: 10pt; padding: 6pt 8pt; border: 0.5pt solid #e2e8f0; border-left: 2pt solid #4338ca; background: #fafbff; }
        .device-block:first-of-type { margin-top: 4pt; }
        .device-block .device-head { display: flex; align-items: baseline; gap: 10pt; margin-bottom: 4pt; }
        .device-block .device-name { font-size: 11pt; font-weight: 700; color: #0f172a; }
        .device-block .ports-label { margin: 6pt 0 2pt; font-weight: 700; font-size: 8pt; color: #4338ca; text-transform: uppercase; letter-spacing: 0.6pt; }

        /* ── Floorplan ─────────────────────────────────────────────── */
        .floorplan { margin: 2pt 0 4pt; }
        .floorplan svg { width: 100%; max-width: 100%; max-height: 180mm; height: auto; display: block; margin: 0 auto; }
    </style>
</head>
<body>
    @php
        $parameters = is_array($document->parameters) ? $document->parameters : [];
        $opts       = is_array($options ?? null) ? $options : (is_array($parameters['options'] ?? null) ? $parameters['options'] : []);
        $includeCover = (bool) ($opts['include_cover'] ?? true);
        $includeToc   = (bool) ($opts['include_toc']   ?? true);
        $sections     = is_array($parameters['sections'] ?? null) ? $parameters['sections'] : [];
        $hierarchy    = $data['hierarchy'] ?? collect();
    @endphp

    @if ($includeCover)
        @include('exports.document.cover')
    @endif

    @if ($includeToc)
        @include('exports.document.toc', ['hierarchy' => $hierarchy, 'data' => $data])
    @endif

    @if ($hierarchy->isNotEmpty())
        @include('exports.document.hierarchy', ['hierarchy' => $hierarchy, 'options' => $opts])
    @endif

    @if (! is_null($data['wifi'] ?? null) && $data['wifi']->isNotEmpty())
        @include('exports.document.wifi', ['wifi' => $data['wifi'], 'description' => $sections['wifi']['description'] ?? ''])
    @endif

    @if (! is_null($data['vpn'] ?? null) && (($data['vpn']['remote'] ?? collect())->isNotEmpty() || ($data['vpn']['site'] ?? collect())->isNotEmpty()))
        @include('exports.document.vpn', ['vpn' => $data['vpn'], 'description' => $sections['vpn']['description'] ?? ''])
    @endif

    @if (! is_null($data['topologies']) && $data['topologies']->isNotEmpty())
        @include('exports.document.topologies', ['topologies' => $data['topologies'], 'description' => $sections['topologies']['description'] ?? ''])
    @endif
</body>
</html>
