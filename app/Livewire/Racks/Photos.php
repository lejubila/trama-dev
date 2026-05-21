<?php

declare(strict_types=1);

namespace App\Livewire\Racks;

use App\Models\Rack;
use App\Models\RackPhoto;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Photos extends Component
{
    use WithFileUploads;

    public Rack $rack;

    /** @var array<int, TemporaryUploadedFile> */
    public array $newPhotos = [];

    public int $lightboxIndex = -1;

    public ?int $editingId = null;

    public string $captionDraft = '';

    public function mount(Rack $rack): void
    {
        $this->authorize('view', $rack);
        $this->rack = $rack;
    }

    public function savePhotos(): void
    {
        $this->authorize('update', $this->rack);

        $this->validate([
            'newPhotos' => 'required|array|min:1|max:10',
            'newPhotos.*' => 'image|max:8192',
        ]);

        $tenantId = TenantContext::id();
        abort_if($tenantId === null, 403);

        $userId = auth()->id();
        $rackId = (int) $this->rack->id;
        $storedPaths = [];

        try {
            DB::transaction(function () use (&$storedPaths, $tenantId, $rackId, $userId): void {
                foreach ($this->newPhotos as $file) {
                    $path = $file->store("rack-photos/{$tenantId}/{$rackId}", 'public');
                    $storedPaths[] = $path;
                    RackPhoto::create([
                        'rack_id' => $rackId,
                        'photo_path' => $path,
                        'created_by' => $userId,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            // Roll back any files that were moved before the DB failure.
            foreach ($storedPaths as $p) {
                Storage::disk('public')->delete($p);
            }
            throw $e;
        }

        $count = count($storedPaths);
        $this->newPhotos = [];
        $this->dispatch('toast', type: 'success', message: $count.' '.($count === 1 ? 'foto caricata.' : 'foto caricate.'));
    }

    public function deletePhoto(int $id): void
    {
        $this->authorize('update', $this->rack);

        $photo = RackPhoto::query()->where('rack_id', $this->rack->id)->find($id);
        if ($photo === null) {
            return;
        }

        if ($photo->photo_path !== '' && Storage::disk('public')->exists($photo->photo_path)) {
            Storage::disk('public')->delete($photo->photo_path);
        }
        $photo->delete();

        // Keep lightbox state coherent if the deleted photo was open.
        if ($this->lightboxIndex >= 0) {
            $remaining = $this->rack->photos()->count();
            if ($remaining === 0) {
                $this->lightboxIndex = -1;
            } elseif ($this->lightboxIndex >= $remaining) {
                $this->lightboxIndex = $remaining - 1;
            }
        }

        $this->dispatch('toast', type: 'success', message: 'Foto eliminata.');
    }

    public function openLightbox(int $index): void
    {
        $this->lightboxIndex = $index;
        $this->editingId = null;
    }

    public function closeLightbox(): void
    {
        $this->lightboxIndex = -1;
        $this->editingId = null;
    }

    public function prev(): void
    {
        if ($this->lightboxIndex < 0) {
            return;
        }
        $count = $this->rack->photos()->count();
        if ($count === 0) {
            $this->lightboxIndex = -1;

            return;
        }
        $this->lightboxIndex = ($this->lightboxIndex - 1 + $count) % $count;
        $this->editingId = null;
    }

    public function next(): void
    {
        if ($this->lightboxIndex < 0) {
            return;
        }
        $count = $this->rack->photos()->count();
        if ($count === 0) {
            $this->lightboxIndex = -1;

            return;
        }
        $this->lightboxIndex = ($this->lightboxIndex + 1) % $count;
        $this->editingId = null;
    }

    public function startEditCaption(int $id): void
    {
        $this->authorize('update', $this->rack);
        $photo = RackPhoto::query()->where('rack_id', $this->rack->id)->find($id);
        if ($photo === null) {
            return;
        }
        $this->editingId = $id;
        $this->captionDraft = (string) ($photo->caption ?? '');
    }

    public function cancelEditCaption(): void
    {
        $this->editingId = null;
        $this->captionDraft = '';
    }

    public function saveCaption(): void
    {
        $this->authorize('update', $this->rack);
        if ($this->editingId === null) {
            return;
        }
        $this->validate(['captionDraft' => 'nullable|string|max:500']);
        $photo = RackPhoto::query()->where('rack_id', $this->rack->id)->find($this->editingId);
        if ($photo === null) {
            $this->editingId = null;

            return;
        }
        $photo->update(['caption' => $this->captionDraft !== '' ? $this->captionDraft : null]);
        $this->editingId = null;
        $this->captionDraft = '';
    }

    public function render(): View
    {
        $photos = $this->rack->photos()->get();

        return view('livewire.racks.photos', [
            'photos' => $photos,
        ]);
    }
}
