<div>
    <x-page-header :title="__('tenants.title')" :subtitle="__('tenants.subtitle')">
        @can('create', App\Models\Tenant::class)
            <a href="{{ route('tenants.import') }}" wire:navigate class="inline-flex items-center gap-x-1.5 rounded-md bg-white dark:bg-slate-700 px-3 py-2 text-sm font-medium text-gray-700 dark:text-slate-200 shadow-sm ring-1 ring-gray-300 dark:ring-slate-600 hover:bg-gray-50 dark:hover:bg-slate-600">
                {{ __('tenants.import') }}
            </a>
            <button wire:click="openCreate" data-guide="tenants.new" class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                <x-icon name="plus" class="h-4 w-4" /> {{ __('tenants.new') }}
            </button>
        @endcan
    </x-page-header>

    <div class="bg-white dark:bg-slate-800 shadow ring-1 ring-black ring-opacity-5 dark:ring-slate-600 rounded-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
            <thead class="bg-gray-50 dark:bg-slate-700/50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">{{ __('common.name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">{{ __('tenants.col_slug') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">{{ __('tenants.col_my_role') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">{{ __('tenants.col_status') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700 text-sm">
                @forelse ($tenants as $tenant)
                    @php $role = $tenant->getRelationValue('pivot')?->getAttribute('role'); @endphp
                    <tr wire:key="t-{{ $tenant->id }}">
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-slate-100">
                            <a href="{{ route('tenants.manage', $tenant) }}" wire:navigate class="text-indigo-700 dark:text-indigo-300 hover:underline">{{ $tenant->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-slate-400 font-mono">{{ $tenant->slug }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-200">{{ $role }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-slate-400">
                            @if ((int) $currentTenantId === (int) $tenant->id)
                                <span class="text-emerald-600 dark:text-emerald-400">{{ __('tenants.active') }}</span>
                            @else
                                <button wire:click="switchTo({{ $tenant->id }})" class="text-xs text-indigo-700 dark:text-indigo-300 hover:underline">{{ __('tenants.activate') }}</button>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right space-x-3">
                            @can('export', $tenant)
                                <a href="{{ route('tenants.export', $tenant) }}" class="text-xs text-gray-600 dark:text-slate-400 hover:underline">{{ __('tenants.export') }}</a>
                            @endcan
                            <a href="{{ route('tenants.manage', $tenant) }}" wire:navigate class="text-xs text-indigo-700 dark:text-indigo-300 hover:underline">{{ __('tenants.manage') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-slate-400">
                            <p class="mb-3">{{ __('tenants.empty') }}</p>
                            @can('create', App\Models\Tenant::class)
                                <button wire:click="openCreate" class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                                    <x-icon name="plus" class="h-4 w-4" /> {{ __('tenants.create_first') }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div data-guide="tenants.form" class="bg-white dark:bg-slate-800 rounded-md shadow-lg w-full max-w-md p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100 mb-4">{{ __('tenants.new') }}</h2>
                <form wire:submit="save" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('common.name') }}</label>
                        <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                        @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('tenants.label_slug_optional') }}</label>
                        <input type="text" wire:model="slug" placeholder="{{ __('tenants.slug_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm font-mono" />
                        @error('slug')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('tenants.label_domain_optional') }}</label>
                        <input type="text" wire:model="domain" placeholder="{{ __('tenants.domain_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                        @error('domain')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <p class="text-xs text-gray-500 dark:text-slate-400">{!! __('tenants.creator_admin_html') !!}</p>
                    <div class="flex justify-end gap-x-2 pt-2">
                        <button type="button" wire:click="$set('showForm', false)" class="px-3 py-2 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-md">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">{{ __('common.create') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
