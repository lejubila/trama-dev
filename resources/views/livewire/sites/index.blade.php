<div>
    <x-page-header :title="__('sites.title')" :subtitle="__('sites.subtitle')">
        @can('create', App\Models\Site::class)
            <button
                type="button"
                wire:click="openCreate"
                class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
            >
                <x-icon name="plus" class="h-4 w-4" />
                {{ __('sites.new') }}
            </button>
        @endcan
    </x-page-header>

    <div class="mb-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('sites.search_placeholder') }}"
            class="block w-64 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
        />
    </div>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('sites.col_name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('sites.col_address') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('sites.col_rooms') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('sites.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                @forelse ($sites as $site)
                    <tr wire:key="site-{{ $site->id }}">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <a href="{{ route('sites.show', $site) }}" wire:navigate class="text-indigo-700 hover:underline">{{ $site->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $site->address ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $site->rooms_count }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            @can('update', $site)
                                <button wire:click="openEdit({{ $site->id }})" class="text-indigo-600 hover:text-indigo-800">
                                    <x-icon name="pencil" class="h-4 w-4 inline" />
                                </button>
                            @endcan
                            @can('delete', $site)
                                <button wire:click="delete({{ $site->id }})" wire:confirm="{{ __('sites.delete_confirm', ['name' => $site->name]) }}" class="text-red-600 hover:text-red-800">
                                    <x-icon name="trash" class="h-4 w-4 inline" />
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ __('sites.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $sites->links() }}</div>

    @if ($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-md shadow-lg w-full max-w-md p-6">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('sites.form_edit') : __('sites.form_new') }}</h2>
                <form wire:submit="save" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('sites.label_name') }}</label>
                        <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('sites.label_address') }}</label>
                        <input type="text" wire:model="address" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('address')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('sites.label_notes') }}</label>
                        <textarea wire:model="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex justify-end gap-x-2 pt-2">
                        <button type="button" wire:click="$set('showForm', false)" class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
