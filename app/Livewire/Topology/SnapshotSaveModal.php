<?php

declare(strict_types=1);

namespace App\Livewire\Topology;

use App\Models\TopologySnapshot;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class SnapshotSaveModal extends Component
{
    public bool $open = false;

    public string $title = '';

    public string $description = '';

    public string $snapshotDate = '';

    /**
     * Base64 data URL captured client-side from cy.png(). Set via Alpine
     * calling $wire.set('snapshotImageBase64', dataUrl). We avoid the
     * Livewire file-upload pipeline (which struggles with very large
     * synthetic blobs) and decode the PNG server-side on save.
     */
    public string $snapshotImageBase64 = '';

    /** @var array<string, mixed> */
    public array $viewState = [];

    public function mount(): void
    {
        $this->snapshotDate = now()->toDateString();
    }

    /**
     * @param  array<string, mixed>  $viewState
     */
    #[On('snapshot-open')]
    public function openModal(array $viewState = []): void
    {
        $this->resetValidation();
        $this->title = '';
        $this->description = '';
        $this->snapshotDate = now()->toDateString();
        $this->snapshotImageBase64 = '';
        $this->viewState = $viewState;
        $this->open = true;

        // Tell the Alpine layer to capture cy.png() and push the data URL
        // onto $snapshotImageBase64 via $wire.set().
        $this->dispatch('snapshot-capture-png', componentId: $this->getId());
    }

    public function close(): void
    {
        $this->open = false;
        $this->snapshotImageBase64 = '';
    }

    public function save()
    {
        $this->authorize('create', TopologySnapshot::class);

        $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'snapshotDate' => 'required|date',
            'snapshotImageBase64' => 'required|string',
        ]);

        $tenantId = TenantContext::id();
        abort_if($tenantId === null, 403);

        $bytes = $this->decodePngDataUrl($this->snapshotImageBase64);
        if ($bytes === null) {
            $this->addError('snapshotImageBase64', 'Immagine non valida.');

            return null;
        }

        $path = "topology-snapshots/{$tenantId}/".Str::uuid().'.png';
        Storage::disk('public')->put($path, $bytes);

        $snap = TopologySnapshot::create([
            'title' => $this->title,
            'description' => $this->description !== '' ? $this->description : null,
            'snapshot_date' => $this->snapshotDate,
            'image_path' => $path,
            'view_state' => $this->viewState,
            'created_by' => auth()->id(),
        ]);

        $this->open = false;
        $this->dispatch('toast', type: 'success', message: 'Snapshot salvato.');

        return $this->redirectRoute('topology.snapshots.show', $snap, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.topology.snapshot-save-modal');
    }

    /**
     * Decode a `data:image/png;base64,...` data URL into raw PNG bytes.
     * Returns null if the input is not a PNG data URL or the base64 decode
     * fails. Also verifies the PNG magic bytes as a basic sanity check.
     */
    private function decodePngDataUrl(string $dataUrl): ?string
    {
        if (! preg_match('#^data:image/png;base64,(.+)$#', $dataUrl, $m)) {
            return null;
        }

        $bytes = base64_decode($m[1], true);
        if ($bytes === false || strlen($bytes) < 8) {
            return null;
        }

        // PNG magic bytes: 89 50 4E 47 0D 0A 1A 0A
        if (substr($bytes, 0, 8) !== "\x89PNG\r\n\x1A\n") {
            return null;
        }

        return $bytes;
    }
}
