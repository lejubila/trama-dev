@props(['field' => 'color', 'presets' => [], 'value' => null])

@php
    $value = $value !== null && $value !== '' ? strtoupper($value) : null;
    $presetsUpper = collect($presets)->map(fn ($hex) => strtoupper($hex));
    $isCustom = $value !== null && ! $presetsUpper->contains($value);
@endphp

<div x-data="{ value: @js($value), open: false }" class="space-y-2">
    <div class="flex flex-wrap gap-2">
        <button
            type="button"
            wire:click="$set('{{ $field }}', '')"
            @click="value = null"
            :class="value === null ? 'ring-2 ring-offset-1 ring-indigo-500' : 'ring-1 ring-gray-300'"
            class="h-7 w-7 rounded-md bg-white relative overflow-hidden"
            title="Nessuno"
        >
            <span class="absolute inset-0 flex items-center justify-center text-gray-400 text-lg leading-none">×</span>
        </button>
        @foreach ($presets as $label => $hex)
            @php $hexU = strtoupper($hex); @endphp
            <button
                type="button"
                wire:click="$set('{{ $field }}', '{{ $hexU }}')"
                @click="value = @js($hexU)"
                :class="value === @js($hexU) ? 'ring-2 ring-offset-1 ring-indigo-500' : 'ring-1 ring-gray-300'"
                class="h-7 w-7 rounded-md"
                style="background-color: {{ $hexU }}"
                title="{{ $label }} ({{ $hexU }})"
            ></button>
        @endforeach
        <label class="inline-flex items-center gap-1 text-xs text-gray-600 cursor-pointer">
            <input
                type="color"
                wire:model.live="{{ $field }}"
                @input="value = $event.target.value.toUpperCase()"
                value="{{ $value ?? '#000000' }}"
                class="h-7 w-7 rounded border border-gray-300 p-0 cursor-pointer"
            />
            <span>custom</span>
        </label>
    </div>
    <div class="text-xs text-gray-500">
        Selezionato:
        <span x-show="value !== null" x-cloak class="inline-flex items-center gap-1">
            <span class="inline-block h-3 w-3 rounded border border-gray-300" :style="`background-color: ${value}`"></span>
            <span class="font-mono" x-text="value"></span>
        </span>
        <span x-show="value === null" class="text-gray-400">nessuno</span>
    </div>
</div>
