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
            <x-cable-fields :color-presets="$colorPresets" :all-tags="$allTags" :color="$color">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('common.status') }}</label>
                    <select wire:model="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        @foreach ($statuses as $s)
                            <option value="{{ $s->value }}">{{ $s->value }}</option>
                        @endforeach
                    </select>
                    @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </x-cable-fields>

            <div class="flex justify-between items-center mt-6">
                <a href="{{ $cancelUrl }}" wire:navigate class="px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">{{ __('common.cancel') }}</a>
                <button type="submit" class="px-3 py-2 text-sm text-white bg-emerald-600 rounded-md hover:bg-emerald-700">{{ __('connections.save_changes') }}</button>
            </div>
        </form>
    </div>
</div>
