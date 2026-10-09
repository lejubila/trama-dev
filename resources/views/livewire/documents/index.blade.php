<div>
    <x-page-header :title="__('documents.title')" :subtitle="__('documents.subtitle')">
        @can('create', App\Models\Document::class)
            <a href="{{ route('documents.create') }}" wire:navigate
               class="text-sm px-3 py-1.5 rounded bg-indigo-600 text-white hover:bg-indigo-700">
                {{ __('documents.new') }}
            </a>
        @endcan
    </x-page-header>

    <div class="flex flex-wrap items-end gap-3 p-3 bg-white shadow ring-1 ring-black/5 rounded-md mb-4">
        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ __('topology.snap_search') }}</label>
            <input type="text" wire:model.live.debounce.400ms="search"
                   class="rounded-md border-gray-300 shadow-sm text-sm" />
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ __('common.date_from') }}</label>
            <input type="date" wire:model.live="dateFrom"
                   class="rounded-md border-gray-300 shadow-sm text-sm" />
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ __('common.date_to') }}</label>
            <input type="date" wire:model.live="dateTo"
                   class="rounded-md border-gray-300 shadow-sm text-sm" />
        </div>
        <button wire:click="clearFilters"
                class="text-xs px-2 py-1 text-gray-500 hover:text-gray-700 underline ml-auto">
            {{ __('common.reset_filters') }}
        </button>
    </div>

    @if ($documents->isEmpty())
        <div class="bg-white shadow ring-1 ring-black/5 rounded-md p-8 text-center text-sm text-gray-500">
            {{ __('documents.empty') }}
            @can('create', App\Models\Document::class)
                <a href="{{ route('documents.create') }}" wire:navigate class="text-indigo-600 hover:underline">{{ __('documents.create_one') }}</a>.
            @endcan
        </div>
    @else
        <div class="bg-white shadow ring-1 ring-black/5 rounded-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('common.title') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('common.date') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('documents.col_generated') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('documents.col_author') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($documents as $doc)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $doc->title }}</div>
                                @if ($doc->description)
                                    <div class="text-xs text-gray-500 truncate max-w-md">{{ $doc->description }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $doc->document_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                @if ($doc->generated_at)
                                    {{ $doc->generated_at->diffForHumans() }}
                                @else
                                    <span class="text-gray-400 italic">{{ __('documents.never') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $doc->creator?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right space-x-2">
                                @if ($doc->pdf_path)
                                    <a href="{{ route('documents.pdf', ['document' => $doc, 'v' => $doc->generated_at?->getTimestamp()]) }}"
                                       class="text-indigo-600 hover:text-indigo-800 text-sm" target="_blank">
                                        {{ __('documents.open_pdf') }}
                                    </a>
                                @endif
                                @can('update', $doc)
                                    <a href="{{ route('documents.edit', $doc) }}" wire:navigate
                                       class="text-gray-600 hover:text-gray-800 text-sm">{{ __('common.edit') }}</a>
                                    <button wire:click="regenerate({{ $doc->id }})"
                                            wire:confirm="{{ __('documents.regenerate_confirm') }}"
                                            class="text-gray-600 hover:text-gray-800 text-sm">{{ __('documents.regenerate') }}</button>
                                @endcan
                                @can('delete', $doc)
                                    <button wire:click="delete({{ $doc->id }})"
                                            wire:confirm="{{ __('documents.delete_confirm') }}"
                                            class="text-red-600 hover:text-red-800 text-sm">{{ __('common.delete') }}</button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $documents->links() }}
        </div>
    @endif
</div>
