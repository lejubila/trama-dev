<div>
    <div
        x-data="{ open: @entangle('open') }"
        x-show="open"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
    >
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl w-full max-w-lg mx-4 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100 mb-4">
                {{ $mode === 'overwrite' ? __('topology.snap_replace_title') : __('topology.snap_save_title') }}
            </h3>

            <form wire:submit="save" class="space-y-4">
                <div class="flex items-center gap-4 text-sm">
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" wire:model.live="mode" value="new" class="text-indigo-600" />
                        {{ __('topology.snap_mode_new') }}
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" wire:model.live="mode" value="overwrite" class="text-indigo-600" />
                        {{ __('topology.snap_mode_replace') }}
                    </label>
                </div>

                @if ($mode === 'overwrite')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">{{ __('topology.snap_to_replace') }}</label>
                        <select wire:model.live="overwriteId"
                                class="w-full rounded-md border-gray-300 dark:bg-slate-900 dark:border-slate-600 shadow-sm text-sm">
                            <option value="">{{ __('common.select_placeholder') }}</option>
                            @foreach ($existingSnapshots as $s)
                                <option value="{{ $s->id }}">{{ $s->title }} · {{ $s->snapshot_date->format('d/m/Y') }}</option>
                            @endforeach
                        </select>
                        @error('overwriteId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">{{ __('common.title') }}</label>
                    <input type="text" wire:model="title" autofocus
                           class="w-full rounded-md border-gray-300 dark:bg-slate-900 dark:border-slate-600 shadow-sm text-sm" />
                    @error('title') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">{{ __('common.description_optional') }}</label>
                    <textarea wire:model="description" rows="3"
                              class="w-full rounded-md border-gray-300 dark:bg-slate-900 dark:border-slate-600 shadow-sm text-sm"></textarea>
                    @error('description') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">{{ __('common.date') }}</label>
                    <input type="date" wire:model="snapshotDate"
                           class="rounded-md border-gray-300 dark:bg-slate-900 dark:border-slate-600 shadow-sm text-sm" />
                    @error('snapshotDate') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="text-xs text-gray-500 dark:text-slate-400">
                    @if ($snapshotImageBase64 !== '')
                        {{ __('topology.snap_image_ready', ['kb' => round(strlen($snapshotImageBase64) * 3 / 4 / 1024)]) }}
                    @else
                        {{ __('topology.snap_waiting') }}
                    @endif
                    @error('snapshotImageBase64') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" wire:click="close"
                            class="px-3 py-1.5 text-sm rounded border bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-200">{{ __('common.cancel') }}</button>
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            :disabled="!$wire.snapshotImageBase64 || ($wire.mode === 'overwrite' && !$wire.overwriteId)"
                            x-data
                            class="px-3 py-1.5 text-sm rounded bg-indigo-600 text-white disabled:opacity-50">
                        {{ $mode === 'overwrite' ? __('topology.snap_replace') : __('common.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
