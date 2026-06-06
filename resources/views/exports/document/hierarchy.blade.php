@php
    $includeFloorplan = (bool) ($options['rooms_include_floorplan'] ?? true);
@endphp
@foreach ($hierarchy as $siteIdx => $siteNode)
    @php $site = $siteNode->site; @endphp
    <section class="section">
        <h2 id="sec-site-{{ $site->id }}">{{ ($siteIdx + 1) }}. Sede — {{ $site->name }}</h2>
        @include('exports.document._metastrip', ['items' => [
            ['Indirizzo', $site->address ?? null],
        ]])
        @if ($siteNode->description)
            <p class="section-description">{{ $siteNode->description }}</p>
        @endif
        @if ($site->notes)
            <p class="small muted">{{ $site->notes }}</p>
        @endif

        @foreach ($siteNode->rooms as $roomIdx => $roomNode)
            @php $room = $roomNode->room; @endphp
            <h3 id="sec-room-{{ $room->id }}">{{ ($siteIdx + 1) }}.{{ ($roomIdx + 1) }} Locale — {{ $room->name }}</h3>
            @include('exports.document._metastrip', ['items' => [
                ['Piano', $room->floor],
                ['Rack', $roomNode->racks->count()],
                ['Dispositivi non in rack', $roomNode->unracked->count()],
            ]])
            @if ($roomNode->description)
                <p class="section-description">{{ $roomNode->description }}</p>
            @endif
            @if ($room->notes)
                <p class="small muted">{{ $room->notes }}</p>
            @endif

            @if ($includeFloorplan)
                @include('exports.document.room-floorplan', ['room' => $room, 'roomNode' => $roomNode])
            @endif

            @foreach ($roomNode->racks as $rackNode)
                @include('exports.document.rack-spread', ['rack' => $rackNode->rack, 'equipment' => $rackNode->equipment, 'options' => $options])
            @endforeach

            @if ($roomNode->unracked->isNotEmpty())
                @include('exports.document.device-list', [
                    'devices' => $roomNode->unracked,
                    'heading' => 'Dispositivi non in rack',
                ])
            @endif
        @endforeach
    </section>
@endforeach
