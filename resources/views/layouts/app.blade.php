<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Trama') }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('logo_favicon.svg') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon_32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon_16.png') }}">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
        <link rel="apple-touch-icon" sizes="256x256" href="{{ asset('favicon_256.png') }}">
        <meta name="theme-color" content="#1565C0">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-gray-100 dark:bg-slate-900 text-gray-900 dark:text-slate-100">
        <div class="min-h-screen"
             x-data="{ sidebar: false, fullscreen: false }"
             x-on:toggle-sidebar.window="sidebar = !sidebar"
             x-on:topology-fullscreen.window="fullscreen = $event.detail.on"
             x-on:keydown.escape.window="sidebar = false"
             x-effect="$dispatch('sidebar-state', { open: sidebar })">
            <!-- Top bar — hidden in topology fullscreen mode -->
            <div x-show="!fullscreen">
                <livewire:layout.navigation />
            </div>

            <!-- Mobile backdrop -->
            <div x-show="sidebar" x-transition.opacity @click="sidebar = false" class="md:hidden fixed inset-0 z-30 bg-black/40" style="display: none"></div>

            <div class="flex">
                <aside
                    x-show="!fullscreen"
                    :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
                    class="fixed z-40 top-0 left-0 h-screen w-64 overflow-y-auto transition-transform duration-200 ease-out
                           md:static md:translate-x-0 md:h-auto md:w-56 md:min-h-[calc(100vh-4rem)]
                           shrink-0 border-r border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800
                           flex flex-col">
                    <nav class="py-4 flex-1" @click="if (window.innerWidth < 768) sidebar = false">
                        <x-nav-section :title="__('nav.section_infrastructure')">
                            <x-nav-item :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">{{ __('nav.dashboard') }}</x-nav-item>
                            <x-nav-item :href="route('sites.index')" :active="request()->routeIs('sites.*')" icon="building">{{ __('nav.sites') }}</x-nav-item>
                            <x-nav-item :href="route('racks.index')" :active="request()->routeIs('racks.*')" icon="server-stack">{{ __('nav.racks') }}</x-nav-item>
                            <x-nav-item :href="route('equipment.index')" :active="request()->routeIs('equipment.*')" icon="cpu">{{ __('nav.equipment') }}</x-nav-item>
                            <x-nav-item :href="route('connections.index')" :active="request()->routeIs('connections.*')" icon="link">{{ __('nav.connections') }}</x-nav-item>
                            <x-nav-item :href="route('topology.index')" :active="request()->routeIs('topology.index')" icon="topology">{{ __('nav.topology') }}</x-nav-item>
                            <x-nav-item :href="route('topology.snapshots.index')" :active="request()->routeIs('topology.snapshots.*')" icon="eye">{{ __('nav.topology_snapshots') }}</x-nav-item>
                            <x-nav-item :href="route('documents.index')" :active="request()->routeIs('documents.*')" icon="document">{{ __('nav.documents') }}</x-nav-item>
                        </x-nav-section>
                        <x-nav-section :title="__('nav.section_organization')">
                            <x-nav-item :href="route('tenants.index')" :active="request()->routeIs('tenants.*')" icon="building">{{ __('nav.tenants') }}</x-nav-item>
                            @can('viewAny', App\Models\User::class)
                                <x-nav-item :href="route('users.index')" :active="request()->routeIs('users.*')" icon="cpu">{{ __('nav.users') }}</x-nav-item>
                            @endcan
                            <x-nav-item :href="route('tags.index')" :active="request()->routeIs('tags.*')" icon="tag">{{ __('nav.tags') }}</x-nav-item>
                            @can('viewAny', App\Models\DeviceIcon::class)
                                <x-nav-item :href="route('icons.index')" :active="request()->routeIs('icons.*')" icon="cpu">{{ __('nav.icons') }}</x-nav-item>
                            @endcan
                            <x-nav-item :href="route('imports.index')" :active="request()->routeIs('imports.*')" icon="clock">{{ __('nav.imports') }}</x-nav-item>
                            <x-nav-item :href="route('audit.index')" :active="request()->routeIs('audit.*')" icon="clock">{{ __('nav.audit') }}</x-nav-item>
                            <x-nav-item :href="route('settings.api-tokens')" :active="request()->routeIs('settings.*')" icon="link">{{ __('nav.api_tokens') }}</x-nav-item>
                        </x-nav-section>
                    </nav>
                    <livewire:layout.profile-menu />
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
