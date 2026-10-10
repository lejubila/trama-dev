<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Support\Guide\GuideTours;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Lets the guided tour (resources/js/guide) advance when the user creates
 * something. Harmless when no tour is running.
 *
 * Two channels, deduplicated by the nonce on the browser side: a Livewire
 * event for saves that stay on the page, and the session for saves that
 * redirect (the event would be lost with the page), read back by the
 * <x-guide> component on the next render.
 */
trait NotifiesGuide
{
    protected function guideCreated(string $entity, Model $model): void
    {
        $payload = ['entity' => $entity, 'id' => (int) $model->getKey(), 'nonce' => Str::random(12)];

        session()->put(GuideTours::LAST_CREATED_KEY, $payload);
        $this->dispatch('guide-created', ...$payload);
    }
}
