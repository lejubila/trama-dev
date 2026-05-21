{{-- Renders a list of devices; for each device with interfaces, a ports
     sub-table is shown including, per connected port, the far-side endpoint.
     Vars: $devices (Collection<Equipment>), $heading (string). --}}
@php
    $connectionFor = function ($iface) {
        $conns = collect();
        if ($iface->relationLoaded('outgoingConnections')) {
            $conns = $conns->merge($iface->outgoingConnections);
        }
        if ($iface->relationLoaded('incomingConnections')) {
            $conns = $conns->merge($iface->incomingConnections);
        }
        $c = $conns->first(fn ($cc) => ($cc->status?->value ?? 'active') === 'active') ?? $conns->first();
        if ($c === null) {
            return null;
        }
        $other = $c->otherEndpoint($iface);
        $label = $other !== null
            ? (($other->equipment->name ?? '—').' / '.$other->name)
            : '—';

        return ['label' => $label, 'cable' => $c->cable_type ?? null];
    };
@endphp

<h4>{{ $heading }}</h4>
@foreach ($devices as $eq)
    @php
        $uText = null;
        if ($eq->on_top) {
            $uText = 'sopra';
        } elseif ($eq->mounted && $eq->position_u_start) {
            $uText = 'U'.$eq->position_u_start.($eq->position_u_height > 1 ? '–U'.($eq->position_u_start + $eq->position_u_height - 1) : '');
        }
        $vendorModel = trim(($eq->vendor ?? '').' '.($eq->model ?? ''));
        $interfaces = $eq->relationLoaded('interfaces') ? $eq->interfaces : collect();
    @endphp
    @php
        $positionText = $uText;
        if ($eq->position_orient === 'rear') {
            $positionText = ($positionText ? $positionText.' · ' : '').'Rear';
        }
        if (! $positionText) {
            $positionText = $eq->rack_id !== null ? '—' : 'Non in rack';
        }
    @endphp
    <div class="device-block">
        <table class="data">
            <tbody>
                <tr>
                    <th style="width:14%">Dispositivo</th>
                    <td style="width:36%"><span class="device-name">{{ $eq->name }}</span></td>
                    <th style="width:14%">Tipo</th>
                    <td>{{ $eq->type?->label() ?? '—' }}</td>
                </tr>
                <tr>
                    <th>Posizione</th>
                    <td>{{ $positionText }}</td>
                    <th>Stato</th>
                    <td>{{ $eq->status ? ($eq->status->label() ?? $eq->status->value) : '—' }}</td>
                </tr>
                <tr>
                    <th>Vendor / Modello</th>
                    <td>{{ $vendorModel !== '' ? $vendorModel : '—' }}</td>
                    <th>Seriale</th>
                    <td>{{ $eq->serial ?? '—' }}</td>
                </tr>
            </tbody>
        </table>

        @if ($interfaces->isNotEmpty() && ($eq->report_ports ?? true))
            <p class="ports-label">Porte ({{ $interfaces->count() }})</p>
            <table class="data">
                <thead>
                    <tr>
                        <th style="width:14%">Porta</th>
                        <th>Tipo</th>
                        <th>Velocità</th>
                        <th>Media / Conn.</th>
                        <th>VLAN</th>
                        <th>IP</th>
                        <th>Stato</th>
                        <th style="width:24%">Connessa a</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($interfaces as $iface)
                        @php
                            $conn = $connectionFor($iface);
                            $mediaConn = trim(($iface->media?->value ?? '').' '.($iface->connector ?? ''));
                            $vlan = $iface->vlan_default !== null ? (string) $iface->vlan_default : '';
                            if ($iface->vlan_mode) {
                                $vlan = trim($iface->vlan_mode->value.($vlan !== '' ? ' '.$vlan : ''));
                            }
                        @endphp
                        <tr>
                            <td><strong>{{ $iface->name }}</strong></td>
                            <td>{{ $iface->type?->value ?? '—' }}</td>
                            <td>{{ $iface->speed_mbps !== null ? $iface->speed_mbps.' Mbps' : '—' }}</td>
                            <td>{{ $mediaConn !== '' ? $mediaConn : '—' }}</td>
                            <td>{{ $vlan !== '' ? $vlan : '—' }}</td>
                            <td>{{ $iface->ip_address ?? '—' }}</td>
                            <td>{{ $iface->status?->value ?? '—' }}</td>
                            <td>
                                @if ($conn)
                                    {{ $conn['label'] }}@if ($conn['cable']) <span class="muted small">({{ $conn['cable'] }})</span>@endif
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endforeach
