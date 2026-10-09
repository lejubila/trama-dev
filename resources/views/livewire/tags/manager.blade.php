<div>
    <x-page-header :title="__('tags.title')" :subtitle="__('tags.subtitle')" />

    @can('create', App\Models\Tag::class)
        <form wire:submit="save" class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md p-4 mb-6 flex items-end gap-3">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700">{{ __('common.name') }}</label>
                <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('common.color') }}</label>
                <input type="color" wire:model="color" class="mt-1 h-10 w-16 rounded-md border-gray-300 shadow-sm" />
                @error('color')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">{{ __('common.add') }}</button>
        </form>
    @endcan

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
        <ul class="divide-y divide-gray-200">
            @forelse ($tags as $tag)
                <li wire:key="tag-{{ $tag->id }}" class="flex items-center justify-between px-4 py-3 text-sm">
                    <div class="flex items-center gap-x-3">
                        <span class="inline-block h-4 w-4 rounded-full" style="background-color: {{ $tag->color }}"></span>
                        <span class="font-medium text-gray-900">{{ $tag->name }}</span>
                        <span class="font-mono text-xs text-gray-400">{{ $tag->color }}</span>
                    </div>
                    @can('delete', $tag)
                        <button wire:click="delete({{ $tag->id }})" wire:confirm="{{ __('tags.delete_confirm', ['name' => $tag->name]) }}" class="text-red-600 hover:text-red-800">
                            <x-icon name="trash" class="h-4 w-4 inline" />
                        </button>
                    @endcan
                </li>
            @empty
                <li class="px-4 py-6 text-center text-gray-500">{{ __('tags.empty') }}</li>
            @endforelse
        </ul>
    </div>
</div>
