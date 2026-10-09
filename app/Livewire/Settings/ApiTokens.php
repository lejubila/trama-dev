<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Support\Demo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class ApiTokens extends Component
{
    #[Validate('required|string|max:80')]
    public string $name = '';

    /** @var list<string> */
    public array $abilities = ['read'];

    /** Plain-text token shown ONCE right after creation; cleared on next render. */
    public ?string $newPlainToken = null;

    public function mount(): void
    {
        // Tokens are long-lived credentials for the shared demo account.
        abort_if(Demo::enabled(), 403, __('demo.tokens_locked'));
    }

    public function create(): void
    {
        abort_if(Demo::enabled(), 403, __('demo.tokens_locked'));
        $this->validate();

        $token = auth()->user()->createToken(
            name: $this->name,
            abilities: $this->abilities !== [] ? $this->abilities : ['read'],
        );

        $this->newPlainToken = $token->plainTextToken;
        $this->reset(['name']);
        $this->abilities = ['read'];
        $this->dispatch('toast', type: 'success', message: __('settings.toast_token_created'));
    }

    public function revoke(int $id): void
    {
        auth()->user()->tokens()->whereKey($id)->delete();
        $this->dispatch('toast', type: 'success', message: __('settings.toast_token_revoked'));
    }

    public function render(): View
    {
        return view('livewire.settings.api-tokens', [
            'tokens' => auth()->user()->tokens()
                ->select('id', 'name', 'abilities', 'last_used_at', 'created_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }
}
