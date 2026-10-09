<div>
    <x-page-header :title="__('topology.snap_title')" :subtitle="__('topology.snap_subtitle')" />

    <div class="flex flex-wrap items-end gap-3 p-3 bg-white dark:bg-slate-800 shadow ring-1 ring-black/5 rounded-md mb-4">
        <div>
            <label class="block text-xs text-gray-500 dark:text-slate-400 mb-1">{{ __('topology.snap_search') }}</label>
            <input type="text" wire:model.live.debounce.400ms="search"
                   class="rounded-md border-gray-300 dark:bg-slate-900 dark:border-slate-600 shadow-sm text-sm" />
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-slate-400 mb-1">{{ __('common.date_from') }}</label>
            <input type="date" wire:model.live="dateFrom"
                   class="rounded-md border-gray-300 dark:bg-slate-900 dark:border-slate-600 shadow-sm text-sm" />
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-slate-400 mb-1">{{ __('common.date_to') }}</label>
            <input type="date" wire:model.live="dateTo"
                   class="rounded-md border-gray-300 dark:bg-slate-900 dark:border-slate-600 shadow-sm text-sm" />
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-slate-400 mb-1">{{ __('common.site') }}</label>
            <select wire:model.live="siteFilter" class="rounded-md border-gray-300 dark:bg-slate-900 dark:border-slate-600 shadow-sm text-sm">
                <option value="0">{{ __('common.all_female') }}</option>
                @foreach ($sites as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}@if (filled($s->address)) — {{ $s->address }}@endif</option>
                @endforeach
            </select>
        </div>
        <button wire:click="clearFilters" class="text-xs px-2 py-1 text-gray-500 hover:text-gray-700 dark:hover:text-slate-200 underline ml-auto">
            {{ __('common.reset_filters') }}
        </button>
    </div>

    @if ($snapshots->isEmpty())
        <div class="bg-white dark:bg-slate-800 shadow ring-1 ring-black/5 rounded-md p-8 text-center text-sm text-gray-500 dark:text-slate-400">
            {!! __('topology.snap_empty_html', ['url' => route('topology.index')]) !!}
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach ($snapshots as $snap)
                <div class="bg-white dark:bg-slate-800 shadow ring-1 ring-black/5 rounded-md overflow-hidden flex flex-col">
                    <a href="{{ route('topology.snapshots.show', $snap) }}" wire:navigate class="block aspect-video bg-gray-100 dark:bg-slate-900 overflow-hidden">
                        <img src="/storage/{{ $snap->image_path }}" alt="{{ $snap->title }}" class="w-full h-full object-contain" />
                    </a>
                    <div class="p-3 flex-1 flex flex-col gap-1">
                        <a href="{{ route('topology.snapshots.show', $snap) }}" wire:navigate class="text-sm font-semibold text-gray-900 dark:text-slate-100 hover:text-indigo-600 truncate">
                            {{ $snap->title }}
                        </a>
                        <div class="text-xs text-gray-500 dark:text-slate-400 flex items-center justify-between">
                            <span>{{ $snap->snapshot_date->format('d/m/Y') }}</span>
                            @if ($snap->creator)
                                <span class="truncate ml-2">{{ $snap->creator->name }}</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-end gap-2 mt-2">
                            <a href="{{ route('topology.snapshots.show', $snap) }}" wire:navigate
                               class="text-xs px-2 py-1 rounded border bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-200">{{ __('common.open') }}</a>
                            @can('update', $snap)
                                <button type="button"
                                        wire:click="openEdit({{ $snap->id }})"
                                        class="text-xs px-2 py-1 rounded border bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 hover:bg-indigo-50">
                                    {{ __('common.edit') }}
                                </button>
                            @endcan
                            @can('delete', $snap)
                                <button type="button"
                                        wire:click="delete({{ $snap->id }})"
                                        wire:confirm="{{ __('topology.snap_delete_confirm') }}"
                                        class="text-xs px-2 py-1 rounded border border-red-300 bg-white text-red-600 hover:bg-red-50">
                                    {{ __('common.delete') }}
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $snapshots->links() }}
        </div>
    @endif

    @if ($showEditForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white dark:bg-slate-800 rounded-md shadow-lg w-full max-w-lg p-6">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-slate-100">{{ __('topology.snap_edit') }}</h2>
                <form wire:submit="saveEdit" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('common.title') }}</label>
                        <input type="text" wire:model="editTitle"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:bg-slate-900 dark:border-slate-600 shadow-sm text-sm" />
                        @error('editTitle')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('common.description') }}</label>
                        <textarea wire:model="editDescription" rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 dark:bg-slate-900 dark:border-slate-600 shadow-sm text-sm"></textarea>
                        @error('editDescription')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('common.date') }}</label>
                        <input type="date" wire:model="editSnapshotDate"
                               class="mt-1 rounded-md border-gray-300 dark:bg-slate-900 dark:border-slate-600 shadow-sm text-sm" />
                        @error('editSnapshotDate')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeEdit"
                                class="px-3 py-1.5 text-sm rounded border bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-200">{{ __('common.cancel') }}</button>
                        <button type="submit"
                                class="px-3 py-1.5 text-sm rounded bg-indigo-600 text-white hover:bg-indigo-700">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
