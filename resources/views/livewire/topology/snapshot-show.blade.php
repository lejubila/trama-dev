<div>
    <x-page-header :title="$snapshot->title">
        <x-slot:subtitle>
            Snapshot del {{ $snapshot->snapshot_date->format('d/m/Y') }}
            @if ($snapshot->creator) · creato da {{ $snapshot->creator->name }} @endif
        </x-slot:subtitle>
    </x-page-header>

    <div class="flex flex-wrap items-center gap-2 mb-3">
        <a href="{{ route('topology.snapshots.index') }}" wire:navigate
           class="text-xs px-2 py-1 rounded border bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-200">← Lista snapshot</a>

        <a href="{{ $liveUrl }}" wire:navigate
           class="text-xs px-2 py-1 rounded border bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700">
            Apri nella topologia live
        </a>

        <a href="/storage/{{ $snapshot->image_path }}" download="{{ $snapshot->title }}.png"
           class="text-xs px-2 py-1 rounded border bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-200">
            Scarica PNG
        </a>

        @can('delete', $snapshot)
            <button type="button"
                    wire:click="delete"
                    wire:confirm="Eliminare questo snapshot?"
                    class="text-xs px-2 py-1 rounded border border-red-300 bg-white text-red-600 hover:bg-red-50 ml-auto">
                Elimina
            </button>
        @endcan
    </div>

    <div class="bg-white dark:bg-slate-800 shadow ring-1 ring-black/5 rounded-md p-4">
        <div class="bg-gray-100 dark:bg-slate-900 rounded">
            <img src="/storage/{{ $snapshot->image_path }}" alt="{{ $snapshot->title }}" class="max-w-full h-auto mx-auto" />
        </div>

        @if ($snapshot->description)
            <div class="mt-4 text-sm text-gray-700 dark:text-slate-300 whitespace-pre-wrap">
                {{ $snapshot->description }}
            </div>
        @endif
    </div>

    <div class="flex items-center justify-between mt-4 gap-2">
        @if ($prev)
            <a href="{{ route('topology.snapshots.show', $prev) }}" wire:navigate
               class="text-xs px-3 py-2 rounded border bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-200 flex-1 max-w-xs">
                ← {{ $prev->title }}
                <span class="block text-[10px] text-gray-500 dark:text-slate-400 mt-0.5">{{ $prev->snapshot_date->format('d/m/Y') }}</span>
            </a>
        @else
            <span class="flex-1 max-w-xs"></span>
        @endif

        @if ($next)
            <a href="{{ route('topology.snapshots.show', $next) }}" wire:navigate
               class="text-xs px-3 py-2 rounded border bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-200 flex-1 max-w-xs text-right ml-auto">
                {{ $next->title }} →
                <span class="block text-[10px] text-gray-500 dark:text-slate-400 mt-0.5">{{ $next->snapshot_date->format('d/m/Y') }}</span>
            </a>
        @endif
    </div>
</div>
