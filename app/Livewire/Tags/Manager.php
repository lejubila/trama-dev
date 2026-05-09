<?php

declare(strict_types=1);

namespace App\Livewire\Tags;

use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class Manager extends Component
{
    #[Validate('required|string|max:50')]
    public string $name = '';

    #[Validate('required|regex:/^#[0-9a-fA-F]{6}$/')]
    public string $color = '#4f46e5';

    public function save(): void
    {
        $this->authorize('create', Tag::class);
        $this->validate();

        Tag::create([
            'name' => $this->name,
            'color' => $this->color,
        ]);

        $this->reset(['name']);
        $this->color = '#4f46e5';
        $this->dispatch('toast', type: 'success', message: 'Tag creato.');
    }

    public function delete(int $id): void
    {
        $tag = Tag::query()->findOrFail($id);
        $this->authorize('delete', $tag);
        $tag->delete();
        $this->dispatch('toast', type: 'success', message: 'Tag rimosso.');
    }

    public function render(): View
    {
        return view('livewire.tags.manager', [
            'tags' => Tag::query()->orderBy('name')->get(),
        ]);
    }
}
