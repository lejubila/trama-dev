<?php

declare(strict_types=1);

namespace App\Livewire\Layout;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationBell extends Component
{
    public bool $open = false;

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    #[On('notification-read')]
    public function markRead(string $id): void
    {
        auth()->user()?->notifications()->whereKey($id)->update(['read_at' => now()]);
    }

    public function markAllRead(): void
    {
        auth()->user()?->unreadNotifications->markAsRead();
    }

    public function render(): View
    {
        $user = auth()->user();
        /** @var Collection<int, DatabaseNotification> $latest */
        $latest = $user
            ? $user->notifications()->limit(8)->get()
            : new Collection;

        return view('livewire.layout.notification-bell', [
            'latest' => $latest,
            'unread' => $user?->unreadNotifications()->count() ?? 0,
        ]);
    }
}
