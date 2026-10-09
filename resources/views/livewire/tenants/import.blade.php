<div>
    <x-page-header :title="__('tenants.import')" :subtitle="__('tenants.import_subtitle')">
        <a href="{{ route('tenants.index') }}" wire:navigate class="text-sm text-gray-600 dark:text-slate-400 hover:text-gray-800">{{ __('common.cancel_back') }}</a>
    </x-page-header>

    <ol class="flex gap-x-2 mb-6 text-xs max-w-3xl">
        @foreach ([__('tenants.import_step_upload'), __('tenants.import_step_preview')] as $i => $label)
            <li class="flex-1 px-3 py-2 rounded-md text-center {{ $step >= $i + 1 ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 font-medium' : 'bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-400' }}">
                {{ $i + 1 }}. {{ $label }}
            </li>
        @endforeach
    </ol>

    <div class="bg-white dark:bg-slate-800 shadow ring-1 ring-black ring-opacity-5 dark:ring-slate-600 rounded-md p-6 max-w-3xl">
        @if ($step === 1)
            <form wire:submit="toPreview" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('tenants.import_file_label') }}</label>
                    <input type="file" wire:model="archive" accept=".zip,application/zip" class="mt-1 block w-full text-sm dark:text-slate-300" />
                    <div wire:loading wire:target="archive" class="text-xs text-gray-500 mt-1">{{ __('tenants.import_uploading') }}</div>
                    @error('archive')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">{!! __('tenants.import_file_help_html') !!}</p>
                </div>
                <div class="flex justify-end">
                    <button type="submit" wire:loading.attr="disabled" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">{{ __('common.next') }}</button>
                </div>
            </form>
        @else
            <form wire:submit="runImport" class="space-y-6">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">{{ __('tenants.import_new_customer') }}</h3>
                    <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">
                        {!! __('tenants.import_source_html', ['name' => e($source['name']), 'slug' => e($source['slug'])]) !!}@if ($source['exported_at']){{ __('tenants.import_source_date', ['date' => $source['exported_at']]) }}@endif{{ __('tenants.import_editable') }}
                    </p>
                    <div class="mt-3 space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('common.name') }}</label>
                            <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('common.slug') }}</label>
                            <input type="text" wire:model="slug" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm font-mono" />
                            @error('slug')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('common.domain') }}</label>
                            <input type="text" wire:model="domain" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                            @error('domain')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">{{ __('tenants.import_contents') }}</h3>
                    <dl class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-6 text-sm">
                        @foreach ($labels as $key => $label)
                            <div class="flex justify-between border-b border-gray-100 dark:border-slate-700 py-1.5">
                                <dt class="text-gray-600 dark:text-slate-400">{{ $label }}</dt>
                                <dd class="font-medium text-gray-900 dark:text-slate-100 tabular-nums">{{ $counts[$key] ?? 0 }}</dd>
                            </div>
                        @endforeach
                        <div class="flex justify-between border-b border-gray-100 dark:border-slate-700 py-1.5">
                            <dt class="text-gray-600 dark:text-slate-400">{{ __('tenants.import_files') }}</dt>
                            <dd class="font-medium text-gray-900 dark:text-slate-100 tabular-nums">{{ $counts['files'] ?? 0 }}</dd>
                        </div>
                    </dl>
                </div>

                @if ($importError)
                    <p class="text-sm text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-900/20 rounded-md px-3 py-2">{{ $importError }}</p>
                @endif

                <div class="flex justify-between">
                    <button type="button" wire:click="restart" class="px-3 py-2 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-md">{{ __('tenants.import_change_file') }}</button>
                    <button type="submit" wire:loading.attr="disabled" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="runImport">{{ __('tenants.import_run') }}</span>
                        <span wire:loading wire:target="runImport">{{ __('tenants.import_running') }}</span>
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
