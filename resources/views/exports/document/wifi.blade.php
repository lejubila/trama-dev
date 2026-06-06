<section class="section">
    <h2 id="sec-wifi">Reti Wi-Fi</h2>
    @if ($description)
        <p class="section-description">{!! nl2br(e($description)) !!}</p>
    @endif

    <table class="data compact">
        <thead>
            <tr>
                <th>SSID</th>
                <th>Sicurezza</th>
                <th>VLAN</th>
                <th>SSID nascosto</th>
                <th>Access Point</th>
                <th>Client</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($wifi as $w)
                <tr>
                    <td><strong>{{ $w->ssid }}</strong></td>
                    <td>{{ $w->security_type ?: '—' }}</td>
                    <td>{{ $w->vlan_id ?: '—' }}</td>
                    <td>{{ $w->hidden_ssid ? 'Sì' : 'No' }}</td>
                    <td>
                        @forelse ($w->broadcasters as $iface)
                            {{ $iface->equipment?->name }} · {{ $iface->name }}@if (! $loop->last)<br>@endif
                        @empty
                            —
                        @endforelse
                    </td>
                    <td>{{ $w->associations_count }}</td>
                </tr>
                @if ($w->notes)
                    <tr>
                        <td colspan="6" class="muted small">{!! nl2br(e($w->notes)) !!}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</section>
