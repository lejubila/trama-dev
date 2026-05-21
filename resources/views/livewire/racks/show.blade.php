<div>
    <x-page-header :title="$rack->name" :subtitle="$rack->room->name . ' — ' . $rack->room->site->name">
        <a href="{{ route('racks.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-800">{{ __('racks.back') }}</a>
        @can('update', $rack)
            <a href="{{ route('racks.index', ['edit' => $rack->id]) }}" wire:navigate class="text-sm text-indigo-700 hover:text-indigo-900">{{ __('racks.edit') }}</a>
        @endcan
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <livewire:racks.elevation :rack="$rack" :key="'elevation-'.$rack->id" />
        </div>
        <aside class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4 text-sm h-fit">
            <h3 class="font-semibold text-gray-700 mb-2">{{ __('racks.specs') }}</h3>
            <dl class="space-y-1 text-gray-600">
                <div class="flex justify-between"><dt>{{ __('racks.spec_height') }}</dt><dd>{{ $rack->height_units }} U</dd></div>
                <div class="flex justify-between"><dt>{{ __('racks.spec_width') }}</dt><dd>{{ $rack->width_mm ?? '—' }} mm</dd></div>
                <div class="flex justify-between"><dt>{{ __('racks.spec_depth') }}</dt><dd>{{ $rack->depth_mm ?? '—' }} mm</dd></div>
                <div class="flex justify-between"><dt>{{ __('racks.spec_numbering') }}</dt><dd>{{ $rack->numbering->value }}</dd></div>
            </dl>
            @if ($rack->notes)
                <h3 class="font-semibold text-gray-700 mt-4 mb-2">{{ __('racks.notes_heading') }}</h3>
                <p class="text-gray-600 whitespace-pre-line">{{ $rack->notes }}</p>
            @endif
        </aside>
    </div>

    <livewire:racks.photos :rack="$rack" :key="'photos-'.$rack->id" />

    <livewire:equipment.drawer />
</div>
