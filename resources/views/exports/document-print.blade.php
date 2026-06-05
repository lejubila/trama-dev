<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    <style>
        @page { size: A4 portrait; margin: 18mm 15mm; }
        @page topo-landscape { size: A4 landscape; margin: 12mm; }
        @page rack-portrait    { size: A4 portrait;  margin: 15mm; }
        @page photo-landscape  { size: A4 landscape; margin: 12mm; }
        @page photo-portrait   { size: A4 portrait;  margin: 15mm; }

        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; font-family: 'DejaVu Sans', Arial, sans-serif; color: #111827; font-size: 11pt; line-height: 1.45; }
        h1 { font-size: 22pt; margin: 0 0 8pt; color: #0f172a; }
        h2 { font-size: 16pt; margin: 0 0 6pt; color: #0f172a; border-bottom: 1.5pt solid #cbd5e1; padding-bottom: 4pt; }
        h3 { font-size: 13pt; margin: 14pt 0 4pt; color: #1e293b; }
        h4 { font-size: 11pt; margin: 8pt 0 4pt; color: #334155; }
        p  { margin: 0 0 6pt; }
        .muted { color: #6b7280; }
        .small { font-size: 9pt; }
        .page-break { page-break-before: always; }
        .topo-landscape { page: topo-landscape; page-break-before: always; }
        /* Same page rule but without forcing a break: used by the first
           topology of the section so it sits right under the title. */
        .topo-landscape-page { page: topo-landscape; }
        /* Each room starts on a fresh page. */
        .room-page { page-break-before: always; }

        .cover { text-align: center; padding-top: 60mm; }
        .cover h1 { font-size: 30pt; margin-bottom: 16pt; }
        .cover .subtitle { font-size: 14pt; color: #475569; margin-bottom: 30pt; }
        .cover .meta { font-size: 11pt; color: #6b7280; margin-top: 60pt; }
        .cover .description { max-width: 70%; margin: 30pt auto 0; text-align: left; }

        .toc { padding-top: 10mm; }
        .toc h2 { border: none; }
        .toc-list { list-style: none; padding: 0; margin: 0; }
        .toc-list > li { padding: 4pt 0; border-bottom: 0.5pt dotted #cbd5e1; }
        .toc-list ul { list-style: none; padding-left: 14pt; margin: 2pt 0; }
        .toc-list ul li { padding: 2pt 0; border: none; font-size: 10pt; color: #475569; }
        .toc-list ul ul li { padding: 1pt 0; font-size: 9.5pt; color: #64748b; }

        .section { page-break-before: always; }
        .section-description { color: #374151; margin-bottom: 12pt; font-style: italic; }

        table.data { width: 100%; border-collapse: collapse; margin-top: 6pt; font-size: 9.5pt; }
        table.data th, table.data td { border: 0.5pt solid #cbd5e1; padding: 4pt 6pt; text-align: left; vertical-align: top; }
        table.data th { background: #f1f5f9; font-weight: 600; }

        /* Keep each topology's title + image on a single page; the engine
           pushes the whole block to the next page rather than splitting it. */
        .topo-block { break-inside: avoid; page-break-inside: avoid; }
        .topo-image { display: block; max-width: 100%; max-height: 215mm; margin: 6pt auto; }
        .topo-landscape .topo-image,
        .topo-landscape-page .topo-image { max-height: 135mm; }

        /* Rack pages: portrait. Page 1 = specs + front elevation; page 2 =
           rear elevation; then one photo per page; then the device table. */
        .rack-page   { page: rack-portrait; page-break-before: always; }
        .device-page { page: rack-portrait; page-break-before: always; }
        .rack-page .elevation { text-align: center; }
        .rack-page .elevation h4 { margin: 8pt 0 3pt; break-after: avoid; page-break-after: avoid; }
        .rack-page .elevation svg { width: auto; height: auto; max-width: 100%; max-height: 215mm; display: block; margin: 0 auto; }
        /* The front page also carries the specs table, so leave it more room. */
        .rack-page.with-specs .elevation svg { max-height: 165mm; }

        .photo-page-l { page: photo-landscape; page-break-before: always; text-align: center; }
        .photo-page-p { page: photo-portrait;  page-break-before: always; text-align: center; }
        .photo-page-l img, .photo-page-p img { max-width: 100%; display: block; margin: 0 auto; object-fit: contain; border: 0.5pt solid #cbd5e1; }
        .photo-page-l img { max-height: 165mm; }
        .photo-page-p img { max-height: 235mm; }

        /* Device blocks in the equipment list: clearly separated cards. */
        .device-block { margin-top: 18pt; break-inside: avoid; page-break-inside: avoid; }
        .device-block:first-of-type { margin-top: 6pt; }
        .device-block .device-name { font-size: 12pt; font-weight: 700; color: #0f172a; }
        .device-block .ports-label { margin: 6pt 0 2pt; font-weight: 600; font-size: 9.5pt; color: #475569; }
        table.data caption { caption-side: top; text-align: left; }

        .floorplan { margin: 4pt 0 6pt; break-inside: avoid; page-break-inside: avoid; }
        /* Full page width; if the aspect ratio makes it too tall, the height
           cap scales it down (preserveAspectRatio="meet") so it stays on one page. */
        .floorplan svg { width: 100%; max-width: 100%; max-height: 200mm; height: auto; display: block; margin: 0 auto; }
    </style>
</head>
<body>
    @php
        $parameters = is_array($document->parameters) ? $document->parameters : [];
        $options    = is_array($parameters['options'] ?? null) ? $parameters['options'] : [];
        $includeCover = (bool) ($options['include_cover'] ?? true);
        $includeToc   = (bool) ($options['include_toc']   ?? true);
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
        @include('exports.document.hierarchy', ['hierarchy' => $hierarchy])
    @endif

    @if (! is_null($data['topologies']) && $data['topologies']->isNotEmpty())
        @include('exports.document.topologies', ['topologies' => $data['topologies'], 'description' => $sections['topologies']['description'] ?? ''])
    @endif

    @if (! is_null($data['wifi'] ?? null) && $data['wifi']->isNotEmpty())
        @include('exports.document.wifi', ['wifi' => $data['wifi'], 'description' => $sections['wifi']['description'] ?? ''])
    @endif

    @if (! is_null($data['vpn'] ?? null) && (($data['vpn']['remote'] ?? collect())->isNotEmpty() || ($data['vpn']['site'] ?? collect())->isNotEmpty()))
        @include('exports.document.vpn', ['vpn' => $data['vpn'], 'description' => $sections['vpn']['description'] ?? ''])
    @endif
</body>
</html>
