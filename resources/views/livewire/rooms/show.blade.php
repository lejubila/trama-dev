<div>
    <x-page-header
        :title="$room->name"
        :subtitle="$room->site->name
            . ($room->floor ? ' — ' . $room->floor : '')
            . ($room->width_m && $room->depth_m
                ? ' — ' . number_format((float) $room->width_m, 2) . 'm × ' . number_format((float) $room->depth_m, 2) . 'm'
                : '')"
    >
        @can('update', $room)
            <a href="{{ route('rooms.plan.edit', $room) }}"
               class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 text-white text-sm font-medium px-3 py-1.5 hover:bg-indigo-500"
               title="{{ __('rooms.plan_editor_open') }}"
            >
                <x-icon name="pencil" class="h-4 w-4" />
                {{ __('rooms.plan_editor_open') }}
            </a>
        @endcan
        <a href="{{ route('sites.show', $room->site) }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-800">← Torna alla sede</a>
    </x-page-header>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4">
        <livewire:rooms.map :room="$room" :key="'rmap-'.$room->id" />
    </div>
</div>
