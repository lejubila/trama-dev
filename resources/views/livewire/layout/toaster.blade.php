<div
    x-data
    class="pointer-events-none fixed inset-0 z-50 flex flex-col items-end gap-y-2 px-4 py-6 sm:p-6"
    aria-live="assertive"
>
    @foreach ($toasts as $toast)
        <div
            wire:key="{{ $toast['id'] }}"
            x-data="{ visible: true }"
            x-init="setTimeout(() => { visible = false; setTimeout(() => $wire.dismiss('{{ $toast['id'] }}'), 200); }, 4000)"
            x-show="visible"
            x-transition.opacity
            class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-md shadow-lg ring-1 ring-black/5 {{ match ($toast['type']) {
                'success' => 'bg-emerald-50 text-emerald-900',
                'error', 'danger' => 'bg-red-50 text-red-900',
                'warning' => 'bg-amber-50 text-amber-900',
                default => 'bg-white text-gray-900',
            } }}"
        >
            <div class="flex items-start p-4 gap-x-3">
                <div class="flex-1 text-sm">{{ $toast['message'] }}</div>
                <button
                    type="button"
                    wire:click="dismiss('{{ $toast['id'] }}')"
                    class="text-gray-400 hover:text-gray-600 text-sm"
                    aria-label="Chiudi"
                >×</button>
            </div>
        </div>
    @endforeach
</div>
