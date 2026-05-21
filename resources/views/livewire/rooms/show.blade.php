<div>
    <x-page-header
        :title="$room->name"
        :subtitle="$room->site->name
            . ($room->floor ? ' — ' . $room->floor : '')
            . ($room->width_m && $room->depth_m
                ? ' — ' . number_format((float) $room->width_m, 2) . 'm × ' . number_format((float) $room->depth_m, 2) . 'm'
                : '')"
    >
        <a href="{{ route('sites.show', $room->site) }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-800">← Torna alla sede</a>
    </x-page-header>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4">
        <livewire:rooms.map :room="$room" :key="'rmap-'.$room->id" />
    </div>
</div>
