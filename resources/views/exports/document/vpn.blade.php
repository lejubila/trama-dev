<section class="section">
    <h2>VPN</h2>
    @if ($description)
        <p class="section-description">{!! nl2br(e($description)) !!}</p>
    @endif

    @if ($vpn['remote']->isNotEmpty())
        <h3>Remote access (client-to-LAN)</h3>
        <table class="data">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Protocollo</th>
                    <th>Firewall / interfaccia</th>
                    <th>Modalità</th>
                    <th>Rete client (CIDR)</th>
                    <th>VLAN raggiungibili</th>
                    <th>Client</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vpn['remote'] as $v)
                    <tr>
                        <td><strong>{{ $v->name }}</strong></td>
                        <td>{{ $v->protocol?->label() }}</td>
                        <td>{{ $v->firewallInterface?->equipment?->name }} · {{ $v->firewallInterface?->name }}</td>
                        <td>{{ ucfirst($v->routing_mode?->value ?? 'routed') }}</td>
                        <td>{{ $v->client_network_cidr ?: '—' }}</td>
                        <td>{{ is_array($v->routed_vlans) && $v->routed_vlans ? implode(', ', $v->routed_vlans) : '—' }}</td>
                        <td>{{ $v->clients->count() }}</td>
                    </tr>
                    @if ($v->notes)
                        <tr>
                            <td colspan="7" class="muted small">{!! nl2br(e($v->notes)) !!}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($vpn['site']->isNotEmpty())
        <h3 style="margin-top:14pt;">Site-to-Site (LAN-to-LAN)</h3>
        <table class="data">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Protocollo</th>
                    <th>Endpoint A</th>
                    <th>Endpoint B</th>
                    <th>Reti / VLAN esportate</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vpn['site'] as $v)
                    <tr>
                        <td><strong>{{ $v->name }}</strong></td>
                        <td>{{ $v->protocol?->label() }}</td>
                        <td>{{ $v->endpointAInterface?->equipment?->name }} · {{ $v->endpointAInterface?->name }}</td>
                        <td>{{ $v->endpointBInterface?->equipment?->name }} · {{ $v->endpointBInterface?->name }}</td>
                        <td class="small">
                            @php
                                $netsA = is_array($v->routed_networks_a) ? $v->routed_networks_a : [];
                                $netsB = is_array($v->routed_networks_b) ? $v->routed_networks_b : [];
                                $vlansA = is_array($v->routed_vlans_a) ? $v->routed_vlans_a : [];
                                $vlansB = is_array($v->routed_vlans_b) ? $v->routed_vlans_b : [];
                            @endphp
                            <strong>A:</strong>
                            {{ $netsA ? implode(', ', $netsA) : '—' }}
                            @if ($vlansA) (VLAN {{ implode(',', $vlansA) }}) @endif
                            <br>
                            <strong>B:</strong>
                            {{ $netsB ? implode(', ', $netsB) : '—' }}
                            @if ($vlansB) (VLAN {{ implode(',', $vlansB) }}) @endif
                        </td>
                    </tr>
                    @if ($v->notes)
                        <tr>
                            <td colspan="5" class="muted small">{!! nl2br(e($v->notes)) !!}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif
</section>
