<x-app-layout>
    @php
        $stats = [
            'sedi'        => \App\Models\Site::query()->count(),
            'rack'        => \App\Models\Rack::query()->count(),
            'dispositivi' => \App\Models\Equipment::query()->count(),
            'connessioni' => \App\Models\Connection::query()->where('status', 'active')->count(),
        ];
        $tenant = auth()->user()?->currentTenant;
    @endphp

    <x-page-header
        :title="$tenant ? 'Riepilogo · '.$tenant->name : 'Dashboard'"
        subtitle="Stato dell'infrastruttura del cliente attivo"
    />

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        @foreach ($stats as $label => $value)
            <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4">
                <div class="text-xs uppercase tracking-wider text-gray-500">{{ $label }}</div>
                <div class="text-2xl font-semibold text-gray-900 mt-1">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4">
            <h2 class="text-base font-semibold mb-3">Accesso rapido</h2>
            <ul class="space-y-2 text-sm">
                <li><a href="{{ route('sites.index') }}" wire:navigate class="text-indigo-700 hover:underline">→ Gestisci sedi</a></li>
                <li><a href="{{ route('racks.index') }}" wire:navigate class="text-indigo-700 hover:underline">→ Gestisci rack</a></li>
                <li><a href="{{ route('equipment.index') }}" wire:navigate class="text-indigo-700 hover:underline">→ Gestisci dispositivi</a></li>
                <li><a href="{{ route('connections.create') }}" wire:navigate class="text-indigo-700 hover:underline">→ Nuova connessione</a></li>
            </ul>
        </div>
        <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4">
            <h2 class="text-base font-semibold mb-3">Note FASE 4+</h2>
            <p class="text-sm text-gray-600">La vista rack elevation e la topologia Cytoscape arrivano nelle prossime fasi.
            Da Audit puoi già seguire ogni modifica fatta su dispositivi, interfacce e connessioni.</p>
        </div>
    </div>
</x-app-layout>
