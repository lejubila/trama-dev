<?php

declare(strict_types=1);

namespace App\Livewire\Documents;

use App\Models\Document;
use App\Services\Export\DocumentPdfBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $dateFrom = '';

    #[Url(except: '')]
    public string $dateTo = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Document::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function regenerate(int $id, DocumentPdfBuilder $builder): void
    {
        $doc = Document::query()->findOrFail($id);
        $this->authorize('update', $doc);

        try {
            $builder->build($doc);
            $this->dispatch('toast', type: 'success', message: 'PDF rigenerato.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Errore: '.$e->getMessage());
        }
    }

    public function delete(int $id): void
    {
        $doc = Document::query()->findOrFail($id);
        $this->authorize('delete', $doc);

        if ($doc->pdf_path !== null && Storage::disk('public')->exists($doc->pdf_path)) {
            Storage::disk('public')->delete($doc->pdf_path);
        }
        $doc->delete();
        $this->dispatch('toast', type: 'success', message: 'Documento eliminato.');
    }

    public function render(): View
    {
        $documents = Document::query()
            ->with('creator')
            ->when($this->search !== '', fn ($q) => $q->where('title', 'ilike', "%{$this->search}%"))
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('document_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('document_date', '<=', $this->dateTo))
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.documents.index', [
            'documents' => $documents,
        ]);
    }
}
