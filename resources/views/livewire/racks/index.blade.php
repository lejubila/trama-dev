<div>
    <x-page-header :title="__('racks.title')" :subtitle="__('racks.subtitle')">
        @can('create', App\Models\Rack::class)
            <button wire:click="openCreate" class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                <x-icon name="plus" class="h-4 w-4" /> {{ __('racks.new') }}
            </button>
        @endcan
    </x-page-header>

    <div class="flex flex-wrap gap-3 mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('racks.search_placeholder') }}" class="rounded-md border-gray-300 shadow-sm text-sm w-64" />
        <select wire:model.live="roomFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="0">{{ __('racks.all_rooms') }}</option>
            @foreach ($rooms as $room)
                <option value="{{ $room->id }}">{{ $room->name }} — {{ $room->site->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('racks.col_name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('racks.col_room') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('racks.col_u') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('racks.col_equipment') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('racks.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                @forelse ($racks as $rack)
                    <tr wire:key="rack-{{ $rack->id }}">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <a href="{{ route('racks.show', $rack) }}" wire:navigate class="text-indigo-700 hover:underline">{{ $rack->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $rack->room->name }} <span class="text-gray-400">— {{ $rack->room->site->name }}</span></td>
                        <td class="px-4 py-3 text-gray-600">{{ $rack->height_units }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $rack->equipment_count }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            @can('update', $rack)
                                <button wire:click="openEdit({{ $rack->id }})" class="text-indigo-600 hover:text-indigo-800"><x-icon name="pencil" class="h-4 w-4 inline" /></button>
                            @endcan
                            @can('delete', $rack)
                                <button wire:click="delete({{ $rack->id }})" wire:confirm="{{ __('racks.delete_confirm', ['name' => $rack->name]) }}" class="text-red-600 hover:text-red-800"><x-icon name="trash" class="h-4 w-4 inline" /></button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('racks.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $racks->links() }}</div>

    @if ($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-md shadow-lg w-full max-w-lg p-6">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('racks.form_edit') : __('racks.form_new') }}</h2>
                <form wire:submit="save" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('racks.label_room') }}</label>
                        <select wire:model="roomId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">{{ __('racks.select_placeholder') }}</option>
                            @foreach ($rooms as $room)
                                <option value="{{ $room->id }}">{{ $room->name }} — {{ $room->site->name }}</option>
                            @endforeach
                        </select>
                        @error('roomId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('racks.label_name') }}</label>
                            <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('racks.label_numbering') }}</label>
                            <select wire:model="numbering" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="bottom_up">{{ __('racks.numbering_bottom_up') }}</option>
                                <option value="top_down">{{ __('racks.numbering_top_down') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('racks.label_height') }}</label>
                            <input type="number" min="1" max="60" wire:model="heightUnits" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            @error('heightUnits')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('racks.label_width') }}</label>
                            <input type="number" wire:model="widthMm" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('racks.label_depth') }}</label>
                            <input type="number" wire:model="depthMm" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('racks.label_pos_x') }}</label>
                            <input type="number" step="0.01" min="0" wire:model="positionX" placeholder="{{ __('racks.pos_x_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            @error('positionX')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('racks.label_pos_y') }}</label>
                            <input type="number" step="0.01" min="0" wire:model="positionY" placeholder="{{ __('racks.pos_y_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            @error('positionY')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 -mt-1">
                        {{ __('racks.coords_help') }}
                    </p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('racks.label_icon') }}</label>
                        @if ($iconUpload)
                            <div class="mt-1 flex items-center gap-3">
                                <img src="{{ $iconUpload->temporaryUrl() }}" alt="{{ __('racks.icon_preview_alt') }}" class="h-16 w-16 object-contain rounded border border-gray-200 bg-gray-50" />
                                <button type="button" wire:click="$set('iconUpload', null)" class="text-xs text-red-600 hover:underline">{{ __('racks.cancel_upload') }}</button>
                            </div>
                        @elseif ($existingIconPath)
                            <div class="mt-1 flex items-center gap-3">
                                <img src="/storage/{{ ltrim($existingIconPath, '/') }}" alt="{{ __('racks.icon_current_alt') }}" class="h-16 w-16 object-contain rounded border border-gray-200 bg-gray-50" />
                                <button type="button" wire:click="removeIcon" wire:confirm="{{ __('racks.remove_icon_confirm') }}" class="text-xs text-red-600 hover:underline">{{ __('racks.remove') }}</button>
                            </div>
                        @endif
                        <input type="file" accept="image/*" wire:model="iconUpload" class="mt-1 block w-full text-sm" />
                        <p class="text-xs text-gray-500 mt-1">{{ __('racks.icon_help') }}</p>
                        @error('iconUpload')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        <div wire:loading wire:target="iconUpload" class="text-xs text-indigo-600 mt-1">{{ __('racks.uploading') }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('racks.label_notes') }}</label>
                        <textarea wire:model="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                    </div>
                    <div class="flex justify-end gap-x-2 pt-2">
                        <button type="button" wire:click="$set('showForm', false)" class="px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
