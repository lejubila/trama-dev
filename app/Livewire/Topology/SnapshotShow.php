<?php

declare(strict_types=1);

namespace App\Livewire\Topology;

use App\Models\TopologySnapshot;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SnapshotShow extends Component
{
    public TopologySnapshot $snapshot;

    public function mount(TopologySnapshot $snapshot): void
    {
        $this->authorize('view', $snapshot);
        $this->snapshot = $snapshot;
    }

    public function delete()
    {
        $this->authorize('delete', $this->snapshot);

        if ($this->snapshot->image_path !== '' && Storage::disk('public')->exists($this->snapshot->image_path)) {
            Storage::disk('public')->delete($this->snapshot->image_path);
        }
        $this->snapshot->delete();
        $this->dispatch('toast', type: 'success', message: 'Snapshot eliminato.');

        return $this->redirectRoute('topology.snapshots.index', navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function liveQuery(): array
    {
        $state = is_array($this->snapshot->view_state) ? $this->snapshot->view_state : [];

        $params = [];
        if (! empty($state['siteId'])) {
            $params['siteId'] = (int) $state['siteId'];
        }
        if (! empty($state['statusFilter'])) {
            $params['statusFilter'] = (string) $state['statusFilter'];
        }
        if (! empty($state['vlanFilter'])) {
            $params['vlanFilter'] = (int) $state['vlanFilter'];
        }
        if (! empty($state['layout']) && $state['layout'] !== 'cose-bilkent') {
            $params['layout'] = (string) $state['layout'];
        }
        if (! empty($state['filterTypes']) && is_array($state['filterTypes'])) {
            $params['filterTypes'] = array_values($state['filterTypes']);
        }
        if (! empty($state['includeHidden'])) {
            $params['includeHidden'] = true;
        }
        if (! empty($state['groupByRack'])) {
            $params['groupByRack'] = true;
        }
        if (! empty($state['groupBySite'])) {
            $params['groupBySite'] = true;
        }
        if (! empty($state['roomFilter'])) {
            $params['roomFilter'] = (int) $state['roomFilter'];
        }
        // Only attach the snapshotPreset hint when there are positions to
        // restore; old snapshots without nodePositions keep the URL clean.
        if (! empty($state['nodePositions']) && is_array($state['nodePositions'])) {
            $params['snapshotPreset'] = (int) $this->snapshot->id;
        }

        return $params;
    }

    public function openLive()
    {
        return $this->redirectRoute('topology.index', $this->liveQuery(), navigate: true);
    }

    public function render(): View
    {
        // Prev/next within the same tenant, ordered by (snapshot_date, id) desc.
        // "Prev" is the older snapshot (smaller), "Next" is the newer (larger).
        $base = TopologySnapshot::query();

        $prev = (clone $base)
            ->where(function ($q): void {
                $q->where('snapshot_date', '<', $this->snapshot->snapshot_date)
                    ->orWhere(function ($qq): void {
                        $qq->where('snapshot_date', $this->snapshot->snapshot_date)
                            ->where('id', '<', $this->snapshot->id);
                    });
            })
            ->orderByDesc('snapshot_date')->orderByDesc('id')
            ->first();

        $next = (clone $base)
            ->where(function ($q): void {
                $q->where('snapshot_date', '>', $this->snapshot->snapshot_date)
                    ->orWhere(function ($qq): void {
                        $qq->where('snapshot_date', $this->snapshot->snapshot_date)
                            ->where('id', '>', $this->snapshot->id);
                    });
            })
            ->orderBy('snapshot_date')->orderBy('id')
            ->first();

        return view('livewire.topology.snapshot-show', [
            'prev' => $prev,
            'next' => $next,
            'liveUrl' => route('topology.index', $this->liveQuery()),
        ]);
    }
}
