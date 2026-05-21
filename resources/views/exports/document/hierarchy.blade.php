@foreach ($hierarchy as $siteIdx => $siteNode)
    @php $site = $siteNode->site; @endphp
    <section class="section">
        <h2>{{ ($siteIdx + 1) }}. Sede — {{ $site->name }}</h2>
        @if ($siteNode->description)
            <p class="section-description">{{ $siteNode->description }}</p>
        @endif
        <table class="data">
            <tbody>
                <tr><th style="width:25%">Indirizzo</th><td>{{ $site->address ?? '—' }}</td></tr>
                @if ($site->notes)
                    <tr><th>Note</th><td>{{ $site->notes }}</td></tr>
                @endif
            </tbody>
        </table>

        @foreach ($siteNode->rooms as $roomIdx => $roomNode)
            @php $room = $roomNode->room; @endphp
            <div class="room-page">
            <h3>{{ ($siteIdx + 1) }}.{{ ($roomIdx + 1) }} Locale — {{ $room->name }}</h3>
            @if ($roomNode->description)
                <p class="section-description">{{ $roomNode->description }}</p>
            @endif
            <table class="data">
                <tbody>
                    @if ($room->floor !== null && $room->floor !== '')
                        <tr><th style="width:25%">Piano</th><td>{{ $room->floor }}</td></tr>
                    @endif
                    @if (! empty($room->notes))
                        <tr><th style="width:25%">Note</th><td>{{ $room->notes }}</td></tr>
                    @endif
                    <tr><th style="width:25%">Rack inclusi</th><td>{{ $roomNode->racks->count() }}</td></tr>
                    <tr><th style="width:25%">Dispositivi non in rack</th><td>{{ $roomNode->unracked->count() }}</td></tr>
                </tbody>
            </table>

            @include('exports.document.room-floorplan', ['room' => $room, 'roomNode' => $roomNode])

            @foreach ($roomNode->racks as $rackNode)
                @include('exports.document.rack-spread', ['rack' => $rackNode->rack, 'equipment' => $rackNode->equipment])
            @endforeach

            @if ($roomNode->unracked->isNotEmpty())
                @include('exports.document.device-list', [
                    'devices' => $roomNode->unracked,
                    'heading' => 'Dispositivi non in rack',
                ])
            @endif
            </div>
        @endforeach
    </section>
@endforeach
