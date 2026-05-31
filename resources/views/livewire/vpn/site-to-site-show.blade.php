<div>
    <x-page-header :title="$vpn->name" :subtitle="$vpn->protocol?->label() . ' · ' . __('vpn.subtitle_site')">
        <a href="{{ route('vpns.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-800">← {{ __('vpn.back') }}</a>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4">
            <h3 class="text-sm font-semibold mb-3">{{ __('vpn.endpoint_a_heading') }}</h3>
            <p class="text-sm">
                {{ $vpn->endpointAInterface?->equipment?->name }} ·
                <span class="font-mono">{{ $vpn->endpointAInterface?->name }}</span>
            </p>
            <p class="text-xs text-gray-500 mt-2">
                {{ __('vpn.routed_vlans_label') }}:
                {{ is_array($vpn->routed_vlans_a) ? implode(',', $vpn->routed_vlans_a) : '—' }}
            </p>
        </div>
        <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4">
            <h3 class="text-sm font-semibold mb-3">{{ __('vpn.endpoint_b_heading') }}</h3>
            <p class="text-sm">
                {{ $vpn->endpointBInterface?->equipment?->name }} ·
                <span class="font-mono">{{ $vpn->endpointBInterface?->name }}</span>
            </p>
            <p class="text-xs text-gray-500 mt-2">
                {{ __('vpn.routed_vlans_label') }}:
                {{ is_array($vpn->routed_vlans_b) ? implode(',', $vpn->routed_vlans_b) : '—' }}
            </p>
        </div>
    </div>
    @if ($vpn->notes)
        <div class="mt-4 bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4">
            <h3 class="text-sm font-semibold mb-2">{{ __('vpn.label_notes') }}</h3>
            <p class="text-xs text-gray-600 whitespace-pre-wrap">{{ $vpn->notes }}</p>
        </div>
    @endif
</div>
