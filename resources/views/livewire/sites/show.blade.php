<div>
    <x-page-header :title="$site->name" :subtitle="$site->address ?? 'Sede senza indirizzo'">
        <a href="{{ route('sites.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-800">← Torna alle sedi</a>
    </x-page-header>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4 mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-2">Note</h2>
        <p class="text-sm text-gray-600 whitespace-pre-line">{{ $site->notes ?? '—' }}</p>
    </div>

    <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-semibold">Locali</h2>
        @can('create', App\Models\Room::class)
            <button wire:click="openRoomCreate" class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                <x-icon name="plus" class="h-4 w-4" /> Nuovo locale
            </button>
        @endcan
    </div>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Piano</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rack</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Azioni</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                @forelse ($rooms as $room)
                    <tr wire:key="room-{{ $room->id }}">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $room->name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $room->floor ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $room->racks_count }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            @can('update', $room)
                                <button wire:click="openRoomEdit({{ $room->id }})" class="text-indigo-600 hover:text-indigo-800"><x-icon name="pencil" class="h-4 w-4 inline" /></button>
                            @endcan
                            @can('delete', $room)
                                <button wire:click="deleteRoom({{ $room->id }})" wire:confirm="Eliminare il locale {{ $room->name }}?" class="text-red-600 hover:text-red-800"><x-icon name="trash" class="h-4 w-4 inline" /></button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Nessun locale.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showRoomForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" wire:click.self="$set('showRoomForm', false)">
            <div class="bg-white rounded-md shadow-lg w-full max-w-md p-6">
                <h2 class="text-lg font-semibold mb-4">{{ $editingRoomId ? 'Modifica locale' : 'Nuovo locale' }}</h2>
                <form wire:submit="saveRoom" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nome</label>
                        <input type="text" wire:model="roomName" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('roomName')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Piano</label>
                        <input type="text" wire:model="roomFloor" placeholder="es. Piano 1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('roomFloor')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Note</label>
                        <textarea wire:model="roomNotes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        @error('roomNotes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex justify-end gap-x-2 pt-2">
                        <button type="button" wire:click="$set('showRoomForm', false)" class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md">Annulla</button>
                        <button type="submit" class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Salva</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
