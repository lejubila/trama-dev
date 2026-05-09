<?php

declare(strict_types=1);

namespace App\Livewire\Layout;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class ThemeToggle extends Component
{
    public string $theme = 'system';

    public function mount(): void
    {
        $prefs = (array) (auth()->user()?->preferences ?? []);
        $theme = $prefs['theme'] ?? 'system';
        $this->theme = is_string($theme) && in_array($theme, ['light', 'dark', 'system'], true)
            ? $theme
            : 'system';
    }

    public function setTheme(string $theme): void
    {
        if (! in_array($theme, ['light', 'dark', 'system'], true)) {
            return;
        }

        $this->theme = $theme;

        $user = auth()->user();
        if ($user !== null) {
            $prefs = (array) ($user->preferences ?? []);
            $prefs['theme'] = $theme;
            $user->forceFill(['preferences' => $prefs])->save();
        }

        // Apply immediately on the client so the user sees the change without
        // a full reload. The next page render will already carry the right
        // class via the layout-level FOUC script.
        $this->dispatch('apply-theme', theme: $theme);
    }

    public function render(): View
    {
        return view('livewire.layout.theme-toggle');
    }
}
