@php
    // The TOC uses anchored links + Chromium's target-counter() trick:
    // each leaf renders as "label … <page>" thanks to the `.dots` flex
    // filler and a `.pg` span whose content is filled by Chromium at
    // print time via the @page CSS counters. Plain text remains
    // readable in viewers that ignore those features.
    $linkRow = function (string $href, string $label) {
        return '<a href="#'.$href.'" class="toc-link" data-target="'.$href.'"><span class="label">'.e($label).'</span><span class="dots"></span><span class="pg" data-page-of="'.$href.'"></span></a>';
    };
@endphp
<style>
    /* target-counter() + leader() must live in the same CSS scope where
       the anchors are; embed once here so the rest of the print stylesheet
       stays generic. The `.pg::after` rule resolves the link target's
       page number on Chromium print. */
    .toc-list a .pg::after {
        content: target-counter(attr(data-target), page);
    }
</style>
<section class="toc">
    <h2 id="sec-toc">Indice</h2>
    <ol class="toc-list">
        @foreach ($hierarchy as $siteIdx => $siteNode)
            <li>
                {!! $linkRow('sec-site-'.$siteNode->site->id, ($siteIdx + 1).'. Sede — '.$siteNode->site->name) !!}
                @if ($siteNode->rooms->isNotEmpty())
                    <ul>
                        @foreach ($siteNode->rooms as $roomIdx => $roomNode)
                            <li>
                                {!! $linkRow('sec-room-'.$roomNode->room->id, ($siteIdx + 1).'.'.($roomIdx + 1).' Locale — '.$roomNode->room->name) !!}
                                @if ($roomNode->racks->isNotEmpty() || $roomNode->unracked->isNotEmpty())
                                    <ul>
                                        @foreach ($roomNode->racks as $rackIdx => $rackNode)
                                            <li>
                                                {!! $linkRow('sec-rack-'.$rackNode->rack->id, ($siteIdx + 1).'.'.($roomIdx + 1).'.'.($rackIdx + 1).' Rack '.$rackNode->rack->name) !!}
                                            </li>
                                        @endforeach
                                        @if ($roomNode->unracked->isNotEmpty())
                                            <li>{{ ($siteIdx + 1) }}.{{ ($roomIdx + 1) }}.{{ $roomNode->racks->count() + 1 }} Dispositivi non in rack ({{ $roomNode->unracked->count() }})</li>
                                        @endif
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
        @php $tocNext = $hierarchy->count() + 1; @endphp
        @if (! is_null($data['wifi'] ?? null) && $data['wifi']->isNotEmpty())
            <li>{!! $linkRow('sec-wifi', ($tocNext++).'. Reti Wi-Fi ('.$data['wifi']->count().')') !!}</li>
        @endif
        @if (! is_null($data['vpn'] ?? null) && (($data['vpn']['remote'] ?? collect())->isNotEmpty() || ($data['vpn']['site'] ?? collect())->isNotEmpty()))
            <li>{!! $linkRow('sec-vpn', ($tocNext++).'. VPN ('.(($data['vpn']['remote'] ?? collect())->count() + ($data['vpn']['site'] ?? collect())->count()).')') !!}</li>
        @endif
        @if (! is_null($data['topologies']) && $data['topologies']->isNotEmpty())
            <li>{!! $linkRow('sec-topologies', ($tocNext++).'. Topologie ('.$data['topologies']->count().')') !!}</li>
        @endif
    </ol>
</section>
