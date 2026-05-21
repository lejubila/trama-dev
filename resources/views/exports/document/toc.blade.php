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
        @if (! is_null($data['topologies']) && $data['topologies']->isNotEmpty())
            <li>{{ $hierarchy->count() + 1 }}. Topologie ({{ $data['topologies']->count() }})</li>
        @endif
    </ol>
</section>
