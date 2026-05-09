<?php

declare(strict_types=1);

namespace App\Livewire\Imports;

use App\Models\Import;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public ?int $expandedId = null;

    public function toggle(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function render(): View
    {
        return view('livewire.imports.index', [
            'imports' => Import::query()
                ->with('user')
                ->orderByDesc('created_at')
                ->paginate(20),
        ]);
    }
}
