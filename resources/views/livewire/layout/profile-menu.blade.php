<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="px-3 py-3 border-t border-gray-200 dark:border-slate-700">
    <div class="px-2 mb-2">
        <div class="text-sm font-medium text-gray-800 dark:text-slate-100"
             x-data="{{ json_encode(['name' => auth()->user()->name]) }}"
             x-text="name"
             x-on:profile-updated.window="name = $event.detail.name"></div>
        <div class="text-xs text-gray-500 dark:text-slate-400 truncate">{{ auth()->user()->email }}</div>
    </div>
    <ul class="space-y-0.5">
        <x-nav-item :href="route('profile')" :active="request()->routeIs('profile')" icon="user">
            {{ __('Profile') }}
        </x-nav-item>
        <li>
            <button type="button" wire:click="logout"
                    class="w-full flex items-center gap-x-2 rounded-md px-2 py-1.5 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition">
                <x-icon name="arrow-right-on-rectangle" class="h-4 w-4 shrink-0" />
                <span>{{ __('Log Out') }}</span>
            </button>
        </li>
    </ul>
</div>
