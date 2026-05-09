<div>
    <x-page-header :title="$rack->name" :subtitle="$rack->room->name . ' — ' . $rack->room->site->name">
        <a href="{{ route('racks.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-800">← Torna ai rack</a>
        <a href="{{ route('racks.export.pdf', $rack) }}" class="text-sm text-gray-700 hover:text-gray-900">⤓ PDF</a>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <livewire:racks.elevation :rack="$rack" :key="'elevation-'.$rack->id" />
        </div>
        <aside class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4 text-sm h-fit">
            <h3 class="font-semibold text-gray-700 mb-2">Specifiche</h3>
            <dl class="space-y-1 text-gray-600">
                <div class="flex justify-between"><dt>Altezza</dt><dd>{{ $rack->height_units }} U</dd></div>
                <div class="flex justify-between"><dt>Larghezza</dt><dd>{{ $rack->width_mm ?? '—' }} mm</dd></div>
                <div class="flex justify-between"><dt>Profondità</dt><dd>{{ $rack->depth_mm ?? '—' }} mm</dd></div>
                <div class="flex justify-between"><dt>Numerazione</dt><dd>{{ $rack->numbering->value }}</dd></div>
            </dl>
            @if ($rack->notes)
                <h3 class="font-semibold text-gray-700 mt-4 mb-2">Note</h3>
                <p class="text-gray-600 whitespace-pre-line">{{ $rack->notes }}</p>
            @endif
        </aside>
    </div>

    <livewire:equipment.drawer />
</div>
