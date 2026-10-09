<div>
    @php
        $cancelUrl = $fromEquipmentId
            ? route('equipment.show', ['equipment' => $fromEquipmentId, 'tab' => 'connections'])
            : route('connections.index');
    @endphp
    <x-page-header :title="__('connections.edit_title')" :subtitle="__('connections.edit_subtitle')">
        <a href="{{ $cancelUrl }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-800">{{ __('common.cancel_back') }}</a>
    </x-page-header>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-6 max-w-3xl">
        <div class="bg-gray-50 rounded p-3 mb-4 text-sm">
            <div>
                <span class="text-gray-500">{{ __('connections.from') }}</span>
                {{ $connection->fromInterface->equipment->name }}
                ·
                <span class="font-mono">{{ $connection->fromInterface->name }}</span>
            </div>
            <div>
                <span class="text-gray-500">{{ __('connections.to') }}</span>
                {{ $connection->toInterface->equipment->name }}
                ·
                <span class="font-mono">{{ $connection->toInterface->name }}</span>
            </div>
            <p class="text-xs text-gray-400 mt-1">{{ __('connections.endpoints_locked') }}</p>
        </div>

        <form wire:submit="save">
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
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('common.status') }}</label>
                    <select wire:model="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        @foreach ($statuses as $s)
                            <option value="{{ $s->value }}">{{ $s->value }}</option>
                        @endforeach
                    </select>
                    @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
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

            <div class="flex justify-between items-center mt-6">
                <a href="{{ $cancelUrl }}" wire:navigate class="px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">{{ __('common.cancel') }}</a>
                <button type="submit" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded-md hover:bg-emerald-700">{{ __('connections.save_changes') }}</button>
            </div>
        </form>
    </div>
</div>
