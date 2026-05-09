<?php

declare(strict_types=1);

namespace App\Livewire\Layout;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Toaster extends Component
{
    /**
     * @var list<array{id: string, type: string, message: string}>
     */
    public array $toasts = [];

    /**
     * Listen for `toast` events dispatched anywhere in the Livewire tree:
     *   $this->dispatch('toast', type: 'success', message: 'Saved.');
     */
    #[On('toast')]
    public function push(string $type = 'info', string $message = ''): void
    {
        $this->toasts[] = [
            'id' => uniqid('t', true),
            'type' => $type,
            'message' => $message,
        ];
    }

    public function dismiss(string $id): void
    {
        $this->toasts = array_values(array_filter(
            $this->toasts,
            fn (array $t): bool => $t['id'] !== $id,
        ));
    }

    public function render(): View
    {
        return view('livewire.layout.toaster');
    }
}
