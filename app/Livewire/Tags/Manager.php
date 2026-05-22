<?php

declare(strict_types=1);

namespace App\Livewire\Tags;

use App\Models\Tag;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Manager extends Component
{
    public string $name = '';

    public string $color = '#4f46e5';

    public function save(): void
    {
        $this->authorize('create', Tag::class);

        $this->validate([
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('tags', 'name')
                    ->where(fn ($q) => $q->where('tenant_id', TenantContext::id())),
            ],
            'color' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
        ], [
            'name.unique' => 'Esiste già un tag con questo nome.',
        ]);

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
