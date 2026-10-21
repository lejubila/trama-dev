{{-- Cable fields shared by the connection wizard and edit page; the slot
     sits after the date, for fields only one of them has. --}}
@props(['colorPresets', 'allTags', 'color' => ''])

<div class="grid grid-cols-2 gap-3">
    <div>
        <label class="block text-sm font-medium text-gray-700">{{ __('connections.label_cable_type') }}</label>
        <select wire:model="cableType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
            <option value="utp_cat6">utp_cat6</option>
            <option value="utp_cat6a">utp_cat6a</option>
            <option value="stp">stp</option>
            <option value="fiber_om3">fiber_om3</option>
            <option value="fiber_om4">fiber_om4</option>
            <option value="dac">dac</option>
        </select>
        @error('cableType')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">{{ __('connections.label_length') }}</label>
        <input type="number" step="0.1" wire:model="cableLengthM" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
        @error('cableLengthM')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="col-span-2">
        <label class="block text-sm font-medium text-gray-700">{{ __('common.label') }}</label>
        <input type="text" wire:model="cableLabel" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
        @error('cableLabel')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.color') }}</label>
        <x-cable-color-picker :presets="$colorPresets" :value="$color" />
        @error('color')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">{{ __('connections.label_established') }}</label>
        <input type="date" wire:model="establishedAt" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
        @error('establishedAt')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    {{ $slot }}
    <div class="col-span-2">
        <label class="block text-sm font-medium text-gray-700">{{ __('common.notes') }}</label>
        <textarea wire:model="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
        @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.tags') }}</label>
        <x-tag-selector :tags="$allTags" model="selectedTagIds" />
        @error('selectedTagIds')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
</div>
