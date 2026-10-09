<?php

use App\Livewire\Forms\LoginForm;
use App\Models\User;
use App\Support\Demo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * One-click access to the shared demo account (demo mode only).
     */
    public function loginAsDemo(): void
    {
        abort_unless(Demo::enabled(), 404);

        $key = 'demo-login:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages([
                'form.email' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }
        RateLimiter::hit($key, 60);

        $user = User::query()->where('email', config('demo.user.email'))->first();
        if ($user === null) {
            throw ValidationException::withMessages(['form.email' => __('demo.not_available')]);
        }

        Auth::login($user);
        Session::regenerate();

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @demo
        <div class="mb-6 rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <h2 class="text-base font-semibold">{{ __('demo.login_title') }}</h2>
            <p class="mt-1">{{ __('demo.login_text', ['time' => App\Support\Demo::resetTimeLabel()]) }}</p>
            <button type="button" wire:click="loginAsDemo" wire:loading.attr="disabled"
                    class="mt-3 w-full inline-flex justify-center rounded-md bg-amber-600 px-4 py-2 font-semibold text-white hover:bg-amber-700 disabled:opacity-50">
                {{ __('demo.login_button') }}
            </button>
            <p class="mt-2 text-xs text-amber-800">{{ __('demo.login_credentials', ['email' => config('demo.user.email'), 'password' => config('demo.user.password')]) }}</p>
        </div>
    @enddemo

    <form wire:submit="login">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="form.password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request') && ! App\Support\Demo::enabled())
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</div>
