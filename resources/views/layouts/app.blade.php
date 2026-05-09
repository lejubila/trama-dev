<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Trama') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            <livewire:layout.navigation />

            <div class="flex">
                <aside class="hidden md:block w-56 shrink-0 border-r border-gray-200 bg-white min-h-[calc(100vh-4rem)]">
                    <nav class="py-4">
                        <x-nav-section title="Infrastruttura">
                            <x-nav-item :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">Dashboard</x-nav-item>
                            <x-nav-item :href="route('sites.index')" :active="request()->routeIs('sites.*')" icon="building">Sedi</x-nav-item>
                            <x-nav-item :href="route('racks.index')" :active="request()->routeIs('racks.*')" icon="server-stack">Rack</x-nav-item>
                            <x-nav-item :href="route('equipment.index')" :active="request()->routeIs('equipment.*')" icon="cpu">Dispositivi</x-nav-item>
                            <x-nav-item :href="route('connections.index')" :active="request()->routeIs('connections.*')" icon="link">Connessioni</x-nav-item>
                            <x-nav-item :href="route('topology.index')" :active="request()->routeIs('topology.*')" icon="link">Topologia</x-nav-item>
                        </x-nav-section>
                        <x-nav-section title="Organizzazione">
                            <x-nav-item :href="route('tags.index')" :active="request()->routeIs('tags.*')" icon="tag">Tag</x-nav-item>
                            <x-nav-item :href="route('audit.index')" :active="request()->routeIs('audit.*')" icon="clock">Audit</x-nav-item>
                        </x-nav-section>
                    </nav>
                </aside>

                <div class="flex-1 min-w-0">
                    @if (isset($header))
                        <header class="bg-white shadow">
                            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                                {{ $header }}
                            </div>
                        </header>
                    @endif

                    <main class="py-6 px-4 sm:px-6 lg:px-8">
                        {{ $slot }}
                    </main>
                </div>
            </div>

            <livewire:layout.toaster />
        </div>
        @livewireScripts
    </body>
</html>
