<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $locale = '';

    public function mount(): void
    {
        $this->locale = Auth::user()->preferredLocale() ?? app()->getLocale();
    }

    public function updateLanguage(): void
    {
        $validated = $this->validate([
            'locale' => ['required', 'string', Rule::in(config('app.supported_locales'))],
        ]);

        Auth::user()->setLocalePreference($validated['locale']);

        // Full redirect so SetLocale middleware re-applies the new locale across
        // the whole UI (menu, layout, this page) on the next request.
        $this->redirect(route('profile'), navigate: false);
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('profile.language_title') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('profile.language_description') }}
        </p>
    </header>

    <form wire:submit="updateLanguage" class="mt-6 space-y-6">
        <div>
            <x-input-label for="locale" :value="__('profile.language_label')" />
            <select
                wire:model="locale"
                id="locale"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option value="it">{{ __('profile.language_it') }}</option>
                <option value="en">{{ __('profile.language_en') }}</option>
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('locale')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('common.save') }}</x-primary-button>
        </div>
    </form>
</section>
