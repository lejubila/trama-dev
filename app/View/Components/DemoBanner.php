<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Support\Demo;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Always-visible strip telling visitors this is a demo that gets wiped daily,
 * with a live countdown to the next reset. Renders nothing outside demo mode.
 */
class DemoBanner extends Component
{
    public bool $showRestored = false;

    public function __construct()
    {
        if (! Demo::enabled() || ! request()->hasSession()) {
            return;
        }

        // Tell each visitor once (per session) that a reset happened since
        // their last visit, so vanished changes don't look like a bug.
        $last = Demo::lastResetAt();
        $seen = (int) session('demo.seen_reset', 0);
        if ($last !== null && $last > $seen) {
            $this->showRestored = $seen > 0;
            session()->put('demo.seen_reset', $last);
        }
    }

    public function shouldRender(): bool
    {
        return Demo::enabled();
    }

    public function render(): View
    {
        return view('components.demo-banner', [
            'resetTime' => Demo::resetTimeLabel(),
            'nextResetIso' => Demo::nextResetAt()->toIso8601String(),
        ]);
    }
}
