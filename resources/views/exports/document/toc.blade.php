<section class="toc page-break">
    <h2>Indice</h2>
    <ol class="toc-list">
        @foreach ($hierarchy as $siteIdx => $siteNode)
            <li>
                {{ ($siteIdx + 1) }}. Sede — {{ $siteNode->site->name }}
                @if ($siteNode->rooms->isNotEmpty())
                    <ul>
                        @foreach ($siteNode->rooms as $roomIdx => $roomNode)
                            <li>
                                {{ ($siteIdx + 1) }}.{{ ($roomIdx + 1) }} Locale — {{ $roomNode->room->name }}
                                @if ($roomNode->racks->isNotEmpty() || $roomNode->unracked->isNotEmpty())
                                    <ul>
                                        @foreach ($roomNode->racks as $rackIdx => $rackNode)
                                            <li>{{ ($siteIdx + 1) }}.{{ ($roomIdx + 1) }}.{{ ($rackIdx + 1) }} Rack {{ $rackNode->rack->name }}</li>
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
        @if (! is_null($data['topologies']) && $data['topologies']->isNotEmpty())
            <li>{{ $tocNext++ }}. Topologie ({{ $data['topologies']->count() }})</li>
        @endif
        @if (! is_null($data['wifi'] ?? null) && $data['wifi']->isNotEmpty())
            <li>{{ $tocNext++ }}. Reti Wi-Fi ({{ $data['wifi']->count() }})</li>
        @endif
        @if (! is_null($data['vpn'] ?? null) && (($data['vpn']['remote'] ?? collect())->isNotEmpty() || ($data['vpn']['site'] ?? collect())->isNotEmpty()))
            <li>{{ $tocNext++ }}. VPN ({{ ($data['vpn']['remote'] ?? collect())->count() + ($data['vpn']['site'] ?? collect())->count() }})</li>
        @endif
    </ol>
</section>
