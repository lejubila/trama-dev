<div
    x-data="demoCountdown({ target: @js($nextResetIso), labels: { h: @js(__('demo.unit_h')), min: @js(__('demo.unit_min')) } })"
    @if ($showRestored) x-init="setTimeout(() => window.Livewire?.dispatch('toast', { type: 'warning', message: @js(__('demo.toast_restored')) }), 300)" @endif
    class="w-full bg-amber-400 text-amber-950 text-sm px-4 py-2 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-center"
    role="status"
>
    <span class="inline-flex items-center rounded bg-amber-950 text-amber-300 px-1.5 py-0.5 text-xs font-bold tracking-wider">{{ __('demo.badge') }}</span>
    <span>{!! __('demo.banner_html', ['time' => e($resetTime)]) !!}</span>
    <span class="whitespace-nowrap font-medium" x-show="remaining" x-cloak>{{ __('demo.next_reset') }} <span x-text="remaining"></span>.</span>
</div>
