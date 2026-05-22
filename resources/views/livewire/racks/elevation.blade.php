<div>
    <div class="flex items-center justify-between mb-3">
        <div class="text-sm text-gray-600 dark:text-slate-400">
            {{ __('racks.elev_hint') }}@if ($canEdit){{ __('racks.elev_hint_add') }}@endif
        </div>
        <div class="inline-flex rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 p-0.5 text-xs">
            <button
                wire:click="setOrient('front')"
                class="px-3 py-1 rounded {{ $orient === 'front' ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-slate-300' }}"
            >{{ __('racks.orient_front') }}</button>
            <button
                wire:click="setOrient('rear')"
                class="px-3 py-1 rounded {{ $orient === 'rear' ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-slate-300' }}"
            >{{ __('racks.orient_rear') }}</button>
        </div>
    </div>

    <div
        x-data="rackDnD"
        x-init="init($el)"
        class="bg-gray-50 dark:bg-slate-900 rounded-md p-4 ring-1 ring-black ring-opacity-5 overflow-x-auto"
    >
        <x-rack-elevation :rack="$rack" :orient="$orient" :interactive="$canEdit" />
        <div
            x-ref="ghost"
            x-show="dragging"
            x-cloak
            class="pointer-events-none absolute z-50 px-2 py-1 text-xs font-medium rounded bg-indigo-600 text-white shadow"
            x-text="hint"
        ></div>
    </div>

    @if ($showForm && ($selectedU !== null || $onTop))
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4 overflow-y-auto">
            <div class="bg-white dark:bg-slate-800 rounded-md shadow-lg w-full max-w-2xl p-6 my-8">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100 mb-1">
                    {{ __('racks.new_equipment') }} · {{ $onTop ? __('racks.on_top_label') : 'U'.$selectedU.' · '.($orient === 'rear' ? __('racks.rear') : __('racks.front')) }}
                </h2>
                <p class="text-xs text-gray-500 dark:text-slate-400 mb-4">{{ __('racks.rack_caption', ['name' => $rack->name, 'u' => $rack->height_units]) }}</p>

                <form wire:submit="saveEquipment" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('racks.label_name') }}</label>
                            <input type="text" wire:model="name" autofocus class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('racks.label_type') }}</label>
                            <select wire:model="type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm">
                                @foreach ($types as $t)
                                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                @endforeach
                            </select>
                            @error('type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        @unless ($onTop)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('racks.label_height') }}</label>
                                <input type="number" min="1" max="60" wire:model="positionUHeight" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                                @error('positionUHeight')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('racks.label_position') }}</label>
                                <input type="text" disabled value="U{{ $selectedU }}{{ $positionUHeight > 1 ? '–U'.($selectedU + $positionUHeight - 1) : '' }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-900 dark:text-slate-300 shadow-sm text-sm font-mono" />
                            </div>
                        @else
                            <div class="col-span-2 text-xs text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/30 rounded p-2">
                                {!! __('racks.on_top_note_html', ['strong' => '<strong>'.e(__('racks.on_top_strong')).'</strong>']) !!}
                            </div>
                        @endunless
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('racks.label_vendor') }}</label>
                            <input type="text" wire:model="vendor" placeholder="{{ __('racks.optional_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('racks.label_model') }}</label>
                            <input type="text" wire:model="modelName" placeholder="{{ __('racks.optional_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('racks.label_serial') }}</label>
                            <input type="text" wire:model="serial" placeholder="{{ __('racks.optional_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                            @error('serial')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('racks.label_status') }}</label>
                            <select wire:model="status" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm">
                                @foreach ($statuses as $s)
                                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                                @endforeach
                            </select>
                            @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('racks.label_firmware') }}</label>
                            <input type="text" wire:model="firmware" placeholder="{{ __('racks.optional_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                            @error('firmware')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('racks.label_asset_tag') }}</label>
                            <input type="text" wire:model="assetTag" placeholder="{{ __('racks.optional_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                            @error('assetTag')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('racks.label_mgmt_ip') }}</label>
                            <input type="text" wire:model="managementIp" placeholder="192.168.1.1" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm" />
                            @error('managementIp')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="flex items-center gap-x-6 gap-y-2 flex-wrap pt-1">
                        <label class="inline-flex items-center gap-x-2 text-sm text-gray-700 dark:text-slate-300">
                            <input type="checkbox" wire:model="locked" class="rounded border-gray-300 dark:border-slate-600 text-indigo-600" />
                            {{ __('racks.label_locked') }}
                        </label>
                        <label class="inline-flex items-center gap-x-2 text-sm text-gray-700 dark:text-slate-300">
                            <input type="checkbox" wire:model="hiddenInTopology" class="rounded border-gray-300 dark:border-slate-600 text-indigo-600" />
                            {{ __('racks.label_hidden_topology') }}
                        </label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('racks.label_description') }}</label>
                        <textarea wire:model="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 shadow-sm text-sm"></textarea>
                        @error('description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('racks.label_icon') }}</label>
                        @if ($iconUpload)
                            <div class="mt-1 flex items-center gap-3">
                                <img src="{{ $iconUpload->temporaryUrl() }}" alt="anteprima" class="h-16 w-16 object-contain rounded border border-gray-200 dark:border-slate-600 bg-gray-50 dark:bg-slate-900" />
                                <button type="button" wire:click="$set('iconUpload', null)" class="text-xs text-red-600 hover:underline">{{ __('racks.icon_cancel') }}</button>
                            </div>
                        @endif
                        <input type="file" accept="image/*" wire:model="iconUpload" class="mt-1 block w-full text-sm text-gray-700 dark:text-slate-300" />
                        <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">{{ __('racks.icon_help') }}</p>
                        @error('iconUpload')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        <div wire:loading wire:target="iconUpload" class="text-xs text-indigo-600 mt-1">{{ __('racks.icon_uploading') }}</div>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-slate-400">{{ __('racks.advanced_help') }}</p>
                    <div class="flex justify-end gap-x-2 pt-2">
                        <button type="button" wire:click="closeForm" class="px-3 py-2 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-md">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">{{ __('common.create') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
