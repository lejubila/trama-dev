@props(['resources', 'actions', 'special', 'tenants', 'tenantScope'])

{{-- Resource × action matrix + tenant allow-list; binds to the EditsTokenPermissions trait. --}}
<div {{ $attributes->merge(['class' => 'space-y-5']) }}>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="block text-sm font-medium text-gray-700">{{ __('settings.abilities') }}</span>
            <div class="flex gap-x-2 text-xs">
                <button type="button" wire:click="preset('read')" class="rounded-md px-2 py-1 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('settings.preset_read_only') }}</button>
                <button type="button" wire:click="preset('all')" class="rounded-md px-2 py-1 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('settings.preset_all') }}</button>
                <button type="button" wire:click="preset('none')" class="rounded-md px-2 py-1 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('settings.preset_none') }}</button>
            </div>
        </div>
        <div class="mt-2 overflow-x-auto rounded-md ring-1 ring-gray-200">
            <table class="min-w-full text-sm" data-testid="ability-matrix">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('settings.resource') }}</th>
                        @foreach ($actions as $action)
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">{{ __('settings.token_actions.'.$action) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($resources as $resource => $allowed)
                        <tr wire:key="res-{{ $resource }}">
                            <td class="px-3 py-1.5 text-gray-800">{{ __('settings.token_resources.'.$resource) }}</td>
                            @foreach ($actions as $action)
                                <td class="px-3 py-1.5 text-center">
                                    @if (in_array($action, $allowed, true))
                                        <input type="checkbox" wire:model.live="abilities" value="{{ $resource }}:{{ $action }}"
                                               aria-label="{{ __('settings.token_resources.'.$resource) }} — {{ __('settings.token_actions.'.$action) }}"
                                               class="rounded border-gray-300 text-indigo-600" />
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    @foreach ($special as $ability => $label)
                        <tr wire:key="special-{{ $label }}">
                            <td class="px-3 py-1.5 text-gray-800" colspan="{{ count($actions) }}">
                                {{ __('settings.ability_'.$label) }}
                                <p class="text-xs text-gray-500">{{ __('settings.ability_'.$label.'_hint') }}</p>
                            </td>
                            <td class="px-3 py-1.5 text-center">
                                <input type="checkbox" wire:model.live="abilities" value="{{ $ability }}"
                                       aria-label="{{ __('settings.ability_'.$label) }}" class="rounded border-gray-300 text-amber-600" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @error('abilities')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <span class="block text-sm font-medium text-gray-700">{{ __('settings.token_tenants') }}</span>
        <div class="mt-1 flex flex-wrap gap-x-4 text-sm">
            <label class="inline-flex items-center gap-x-1.5">
                <input type="radio" wire:model.live="tenantScope" value="all" class="border-gray-300 text-indigo-600" />
                {{ __('settings.tenants_all') }}
            </label>
            <label class="inline-flex items-center gap-x-1.5">
                <input type="radio" wire:model.live="tenantScope" value="selected" class="border-gray-300 text-indigo-600" />
                {{ __('settings.tenants_selected') }}
            </label>
        </div>
        @if ($tenantScope === 'selected')
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-1 max-h-40 overflow-y-auto rounded-md p-2 ring-1 ring-gray-200 text-sm">
                @foreach ($tenants as $tenant)
                    <label wire:key="tn-{{ $tenant->id }}" class="inline-flex items-center gap-x-1.5">
                        <input type="checkbox" wire:model="tenantIds" value="{{ $tenant->id }}" class="rounded border-gray-300 text-indigo-600" />
                        {{ $tenant->name }}
                    </label>
                @endforeach
            </div>
        @endif
        @error('tenantIds')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
</div>
