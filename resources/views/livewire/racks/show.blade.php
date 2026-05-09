<div>
    <x-page-header :title="$rack->name" :subtitle="$rack->room->name . ' — ' . $rack->room->site->name">
        <a href="{{ route('racks.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-800">← Torna ai rack</a>
    </x-page-header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4">
            <div class="text-sm text-gray-500 mb-3">Vista rack elevation: arriverà in FASE 4. Per ora elenco testuale.</div>
            <ul class="divide-y divide-gray-200">
                @forelse ($mountedEquipment as $eq)
                    <li wire:key="me-{{ $eq->id }}" class="py-3 flex items-center justify-between">
                        <div>
                            <div class="font-medium text-gray-900">U{{ $eq->position_u_start }}–U{{ $eq->position_u_start + $eq->position_u_height - 1 }}: {{ $eq->name }}</div>
                            <div class="text-xs text-gray-500">{{ $eq->vendor }} {{ $eq->model }} · {{ $eq->type?->label() }}</div>
                        </div>
                        <a href="{{ route('equipment.show', $eq) }}" wire:navigate class="text-sm text-indigo-700 hover:underline">Apri</a>
                    </li>
                @empty
                    <li class="py-6 text-center text-gray-500">Rack vuoto.</li>
                @endforelse
            </ul>
        </div>
        <aside class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4 text-sm">
            <h3 class="font-semibold text-gray-700 mb-2">Specifiche</h3>
            <dl class="space-y-1 text-gray-600">
                <div class="flex justify-between"><dt>Altezza</dt><dd>{{ $rack->height_units }} U</dd></div>
                <div class="flex justify-between"><dt>Larghezza</dt><dd>{{ $rack->width_mm ?? '—' }} mm</dd></div>
                <div class="flex justify-between"><dt>Profondità</dt><dd>{{ $rack->depth_mm ?? '—' }} mm</dd></div>
                <div class="flex justify-between"><dt>Numerazione</dt><dd>{{ $rack->numbering?->value }}</dd></div>
            </dl>
        </aside>
    </div>
</div>
