<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" x-on:sidebar-state.window="open = $event.detail.open" class="bg-white dark:bg-slate-800 border-b border-gray-100 dark:border-slate-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between gap-x-2 h-16">
            <div class="flex shrink-0">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="block h-9 w-auto" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 lg:-my-px lg:ms-10 lg:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </x-nav-link>
                </div>
            </div>

            <!-- Settings: kept on narrow screens too, just more compact -->
            <div class="flex flex-1 min-w-0 items-center justify-end gap-x-2 sm:ms-6 sm:gap-x-4">
                @if (auth()->user()?->canManageData())
                    <button type="button" x-data x-on:click="$store.guide.openMenu()" data-guide="guide.button"
                            title="{{ __('guide.button') }}" aria-label="{{ __('guide.button') }}"
                            class="inline-flex shrink-0 items-center gap-x-1.5 rounded-md px-2 py-1.5 lg:px-2.5 text-sm font-medium text-gray-600 dark:text-slate-300 ring-1 ring-gray-200 dark:ring-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700">
                        <x-icon name="question-mark-circle" class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                        <span class="hidden lg:inline">{{ __('guide.button') }}</span>
                    </button>
                @endif
                <livewire:layout.global-search />
                <livewire:layout.tenant-selector />
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex shrink-0 items-center md:hidden">
                <button @click="$dispatch('toggle-sidebar')" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

</nav>
