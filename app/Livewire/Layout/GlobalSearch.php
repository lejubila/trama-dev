<?php

declare(strict_types=1);

namespace App\Livewire\Layout;

use App\Services\GlobalSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    public bool $open = false;

    /** Max rows per group. */
    private const PER_GROUP = 5;

    public function updatedQuery(string $value): void
    {
        $this->open = strlen(trim($value)) >= GlobalSearchService::MIN_CHARS;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function clear(): void
    {
        $this->query = '';
        $this->open = false;
    }

    public function render(GlobalSearchService $search): View
    {
        $groups = $search->search($this->query, self::PER_GROUP);
        $totalHits = collect($groups)->sum(fn (Collection $g): int => $g->count());

        return view('livewire.layout.global-search', [
            'groups' => $groups,
            'totalHits' => $totalHits,
        ]);
    }
}
