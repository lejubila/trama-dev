@php
    $prefs = (array) (auth()->user()?->preferences ?? []);
    $theme = is_string($prefs['theme'] ?? null) ? $prefs['theme'] : 'system';
    $rootClass = $theme === 'dark' ? 'dark' : '';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $rootClass }}" data-theme="{{ $theme }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Trama') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <script>
            // FOUC-safe: pick the dark class as early as possible. We honor the
            // server-rendered choice when explicit; for `system` we read the OS
            // preference. localStorage acts as a third source for guests.
            (function () {
                var theme = document.documentElement.getAttribute('data-theme');
                if (theme === 'system') {
                    var stored = localStorage.getItem('trama-theme');
                    if (stored === 'dark' || stored === 'light') theme = stored;
                    else theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.classList.toggle('dark', theme === 'dark');
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-gray-100 dark:bg-slate-900 text-gray-900 dark:text-slate-100">
        <div class="min-h-screen">
            <livewire:layout.navigation />

            <div class="flex">
                <aside class="hidden md:block w-56 shrink-0 border-r border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 min-h-[calc(100vh-4rem)]">
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
                            <x-nav-item :href="route('imports.index')" :active="request()->routeIs('imports.*')" icon="clock">Import</x-nav-item>
                            <x-nav-item :href="route('audit.index')" :active="request()->routeIs('audit.*')" icon="clock">Audit</x-nav-item>
                            <x-nav-item :href="route('notifications.index')" :active="request()->routeIs('notifications.*')" icon="clock">Notifiche</x-nav-item>
                            <x-nav-item :href="route('settings.api-tokens')" :active="request()->routeIs('settings.*')" icon="link">API Tokens</x-nav-item>
                        </x-nav-section>
                    </nav>
                </aside>

                <div class="flex-1 min-w-0">
                    @if (isset($header))
                        <header class="bg-white dark:bg-slate-800 shadow">
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
