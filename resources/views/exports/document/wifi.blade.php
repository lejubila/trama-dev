<section class="section">
    <h2 id="sec-wifi">{{ __('exports.wifi') }}</h2>
    @if ($description)
        <p class="section-description">{!! nl2br(e($description)) !!}</p>
    @endif

    <table class="data compact">
        <thead>
            <tr>
                <th>SSID</th>
                <th>{{ __('exports.site') }}</th>
                <th>{{ __('exports.security') }}</th>
                <th>VLAN</th>
                <th>{{ __('exports.hidden_ssid') }}</th>
                <th>{{ __('exports.access_points') }}</th>
                <th>{{ __('exports.clients') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($wifi as $w)
                <tr>
                    <td><strong>{{ $w->ssid }}</strong></td>
                    <td>{{ $w->site?->name ?? '—' }}</td>
                    <td>{{ $w->security_type ?: '—' }}</td>
                    <td>{{ $w->vlan_id ?: '—' }}</td>
                    <td>{{ $w->hidden_ssid ? __('exports.yes') : __('exports.no') }}</td>
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
                        <td colspan="7" class="muted small">{!! nl2br(e($w->notes)) !!}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</section>
