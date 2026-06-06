{{-- Compact inline metadata strip used by sites/rooms/racks/devices.
     $items = list of [label, value]; null/'' values are skipped so the
     strip never shows empty trailing dividers. --}}
@php
    $clean = collect($items)->filter(fn ($row) => isset($row[1]) && $row[1] !== '' && $row[1] !== null)->values();
@endphp
@if ($clean->isNotEmpty())
    <dl class="meta-strip">
        @foreach ($clean as $row)
            <div>
                <dt>{{ $row[0] }}</dt>
                <dd>{{ $row[1] }}</dd>
            </div>
        @endforeach
    </dl>
@endif
