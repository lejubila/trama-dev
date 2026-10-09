@php
    $includeFloorplan = (bool) ($options['rooms_include_floorplan'] ?? true);
@endphp
@foreach ($hierarchy as $siteIdx => $siteNode)
    @php $site = $siteNode->site; @endphp
    <section class="section">
        <h2 id="sec-site-{{ $site->id }}">{{ __('exports.site_heading', ['num' => $siteIdx + 1, 'name' => $site->name]) }}</h2>
        @include('exports.document._metastrip', ['items' => [
            [__('exports.address'), $site->address ?? null],
        ]])
        @if ($siteNode->description)
            <p class="section-description">{{ $siteNode->description }}</p>
        @endif
        @if ($site->notes)
            <p class="small muted">{{ $site->notes }}</p>
        @endif

        @foreach ($siteNode->rooms as $roomIdx => $roomNode)
            @php $room = $roomNode->room; @endphp
            <h3 id="sec-room-{{ $room->id }}">{{ __('exports.room_heading', ['num' => ($siteIdx + 1).'.'.($roomIdx + 1), 'name' => $room->name]) }}</h3>
            @include('exports.document._metastrip', ['items' => [
                [__('exports.floor'), $room->floor],
                [__('exports.rack'), $roomNode->racks->count()],
                [__('exports.unracked'), $roomNode->unracked->count()],
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
                    'heading' => __('exports.unracked'),
                ])
            @endif
        @endforeach
    </section>
@endforeach
