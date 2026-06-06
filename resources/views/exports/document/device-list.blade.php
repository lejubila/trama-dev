{{-- Renders a list of devices as cards. Each card has a header
     (meta-strip) and an optional synthetic ports table. Columns VLAN
     and IP are dropped when ALL ports of the device leave them empty,
     so passive panels and simple appliances don't show a sea of "—". --}}
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

    // Compose "ethernet · copper rj45 @ 1 Gbps" from the iface fields,
    // dropping the parts that aren't set so we don't get stray separators.
    $describeIface = function ($iface) {
        $parts = [];
        if ($iface->type?->value) $parts[] = $iface->type->value;
        $mediaConn = trim(($iface->media?->value ?? '').' '.($iface->connector ?? ''));
        if ($mediaConn !== '') $parts[] = $mediaConn;
        $spec = implode(' · ', $parts);
        if ($iface->speed_mbps !== null) {
            $speedTxt = $iface->speed_mbps >= 1000
                ? rtrim(rtrim(number_format($iface->speed_mbps / 1000, 1, ',', ''), '0'), ',').' Gbps'
                : $iface->speed_mbps.' Mbps';
            $spec .= ($spec !== '' ? ' @ ' : '').$speedTxt;
        }
        return $spec !== '' ? $spec : '—';
    };

    $vlanLabel = function ($iface) {
        $bits = [];
        if ($iface->vlan_mode?->value) $bits[] = $iface->vlan_mode->value;
        if ($iface->vlan_default !== null) $bits[] = (string) $iface->vlan_default;
        return $bits === [] ? '' : implode(' ', $bits);
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

        $positionText = $uText;
        if ($eq->position_orient === 'rear') {
            $positionText = ($positionText ? $positionText.' · ' : '').'Rear';
        }
        if (! $positionText) {
            $positionText = $eq->rack_id !== null ? null : 'Non in rack';
        }

        // Per-device: drop VLAN and IP columns when ALL interfaces leave them empty.
        $showVlan = $interfaces->contains(fn ($i) => $vlanLabel($i) !== '');
        $showIp   = $interfaces->contains(fn ($i) => ! empty($i->ip_address));

        $portCount = $interfaces->count();
        $portsTableClass = $portCount > 12 ? 'data ports-dense' : 'data ports-comfy';
    @endphp
    <div class="device-block">
        <div class="device-head">
            <span class="device-name">{{ $eq->name }}</span>
        </div>
        @include('exports.document._metastrip', ['items' => [
            ['Tipo', $eq->type?->label() ?? null],
            ['Posizione', $positionText],
            ['Stato', $eq->status ? ($eq->status->label() ?? $eq->status->value) : null],
            ['Vendor/Modello', $vendorModel !== '' ? $vendorModel : null],
            ['Seriale', $eq->serial ?? null],
        ]])

        @if ($interfaces->isNotEmpty() && ($eq->report_ports ?? true))
            <p class="ports-label">Porte ({{ $portCount }})</p>
            <table class="{{ $portsTableClass }}">
                <thead>
                    <tr>
                        <th style="width:12%">Porta</th>
                        <th>Specifiche</th>
                        @if ($showVlan)<th style="width:10%">VLAN</th>@endif
                        @if ($showIp)<th style="width:14%">IP</th>@endif
                        <th style="width:8%">Stato</th>
                        <th style="width:22%">Connessa a</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($interfaces as $iface)
                        @php
                            $conn = $connectionFor($iface);
                            $vlan = $vlanLabel($iface);
                        @endphp
                        <tr>
                            <td><strong>{{ $iface->name }}</strong></td>
                            <td>{{ $describeIface($iface) }}</td>
                            @if ($showVlan)<td>{{ $vlan !== '' ? $vlan : '—' }}</td>@endif
                            @if ($showIp)<td>{{ $iface->ip_address ?? '—' }}</td>@endif
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
