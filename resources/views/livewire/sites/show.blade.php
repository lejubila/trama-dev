<div>
    <x-page-header :title="$site->name" :subtitle="$site->address ?? __('sites.no_address')">
        <a href="{{ route('sites.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-800">{{ __('sites.back') }}</a>
    </x-page-header>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4 mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-2">{{ __('sites.notes_heading') }}</h2>
        <p class="text-sm text-gray-600 whitespace-pre-line">{{ $site->notes ?? '—' }}</p>
    </div>

    <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-semibold">{{ __('sites.rooms_heading') }}</h2>
        @can('create', App\Models\Room::class)
            <button wire:click="openRoomCreate" class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                <x-icon name="plus" class="h-4 w-4" /> {{ __('sites.new_room') }}
            </button>
        @endcan
    </div>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('sites.col_name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('sites.col_floor') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('sites.col_racks') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('sites.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                @forelse ($rooms as $room)
                    <tr wire:key="room-{{ $room->id }}">
                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('rooms.show', $room) }}" wire:navigate class="text-indigo-700 hover:underline">{{ $room->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $room->floor ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $room->racks_count }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            @can('update', $room)
                                <button wire:click="openRoomEdit({{ $room->id }})" class="text-indigo-600 hover:text-indigo-800"><x-icon name="pencil" class="h-4 w-4 inline" /></button>
                            @endcan
                            @can('delete', $room)
                                <button wire:click="deleteRoom({{ $room->id }})" wire:confirm="{{ __('sites.delete_room_confirm', ['name' => $room->name]) }}" class="text-red-600 hover:text-red-800"><x-icon name="trash" class="h-4 w-4 inline" /></button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ __('sites.rooms_empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($rooms->isNotEmpty())
        <div class="mt-8">
            <h2 class="text-lg font-semibold mb-3">{{ __('sites.floorplans_heading') }}</h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach ($rooms as $room)
                    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-semibold">
                                <a href="{{ route('rooms.show', $room) }}" wire:navigate class="text-indigo-700 hover:underline">{{ $room->name }}</a>
                            </h3>
                            @can('update', $room)
                                <a href="{{ route('rooms.plan.edit', $room) }}"
                                   class="inline-flex items-center gap-1 rounded-md bg-indigo-600 text-white text-xs font-medium px-2 py-1 hover:bg-indigo-500"
                                   title="{{ __('rooms.plan_editor_open') }}"
                                >
                                    <x-icon name="pencil" class="h-3.5 w-3.5" />
                                    {{ __('rooms.plan_editor_open') }}
                                </a>
                            @endcan
                        </div>
                        <livewire:rooms.map :room="$room" :key="'rmap-'.$room->id" />
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($showRoomForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-md shadow-lg w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $editingRoomId ? __('sites.room_form_edit') : __('sites.room_form_new') }}</h2>
                <form wire:submit="saveRoom" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('sites.label_name') }}</label>
                        <input type="text" wire:model="roomName" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('roomName')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('sites.label_floor') }}</label>
                        <input type="text" wire:model="roomFloor" placeholder="{{ __('sites.floor_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('roomFloor')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('sites.label_width') }}</label>
                            <input type="number" step="0.01" min="0.5" wire:model="roomWidthM" placeholder="{{ __('sites.width_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            @error('roomWidthM')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('sites.label_depth') }}</label>
                            <input type="number" step="0.01" min="0.5" wire:model="roomDepthM" placeholder="{{ __('sites.depth_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            @error('roomDepthM')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 -mt-1">
                        {{ __('sites.dimensions_help') }}
                    </p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('sites.label_floorplan') }}</label>
                        @if ($roomFloorPlan)
                            <div class="mt-1 flex items-center gap-3">
                                <img src="{{ $roomFloorPlan->temporaryUrl() }}" alt="{{ __('sites.floorplan_preview_alt') }}" class="h-24 w-auto rounded border border-gray-200" />
                                <div class="text-xs">
                                    <p class="text-green-700">{{ __('sites.preview_ready') }}</p>
                                    <button type="button" wire:click="$set('roomFloorPlan', null)" class="text-red-600 hover:underline mt-1">{{ __('sites.cancel_upload') }}</button>
                                </div>
                            </div>
                        @elseif ($existingFloorPlanPath)
                            <div class="mt-1 flex items-center gap-3">
                                <img src="/storage/{{ ltrim($existingFloorPlanPath, '/') }}" alt="{{ __('sites.floorplan_current_alt') }}" class="h-24 w-auto rounded border border-gray-200" />
                                <button type="button" wire:click="removeFloorPlan" wire:confirm="{{ __('sites.remove_floorplan_confirm') }}" class="text-xs text-red-600 hover:underline">{{ __('sites.remove') }}</button>
                            </div>
                        @endif
                        <input type="file" accept="image/*" wire:model="roomFloorPlan" class="mt-1 block w-full text-sm" />
                        <p class="text-xs text-gray-500 mt-1">{{ __('sites.floorplan_help') }}</p>
                        @error('roomFloorPlan')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        <div wire:loading wire:target="roomFloorPlan" class="text-xs text-indigo-600 mt-1">{{ __('sites.uploading') }}</div>
                        @if ($editingRoomId !== null)
                            <a href="{{ route('rooms.plan.edit', $editingRoomId) }}" class="inline-block mt-2 text-xs text-indigo-600 hover:underline">
                                {{ __('rooms.plan_editor_open') }}
                            </a>
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('sites.label_notes') }}</label>
                        <textarea wire:model="roomNotes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        @error('roomNotes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex justify-end gap-x-2 pt-2">
                        <button type="button" wire:click="$set('showRoomForm', false)" class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
