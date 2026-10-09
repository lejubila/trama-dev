<div>
    <x-page-header :title="__('equipment.title')" :subtitle="__('equipment.subtitle')">
        <a href="{{ route('export.equipment.csv') }}" class="text-sm text-gray-700 hover:text-gray-900 inline-flex items-center gap-x-1">{{ __('equipment.export_csv') }}</a>
        @can('create', App\Models\Equipment::class)
            <a href="{{ route('equipment.import') }}" wire:navigate class="text-sm text-gray-700 hover:text-gray-900 inline-flex items-center gap-x-1">{{ __('equipment.import_csv') }}</a>
            <button wire:click="openCreate" class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                <x-icon name="plus" class="h-4 w-4" /> {{ __('equipment.new') }}
            </button>
        @endcan
    </x-page-header>

    <div class="flex flex-wrap gap-3 mb-4 items-center">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('equipment.search_placeholder') }}" class="rounded-md border-gray-300 shadow-sm text-sm w-64" />
        <select wire:model.live="typeFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">{{ __('common.all_types') }}</option>
            @foreach (\App\Enums\EquipmentType::groupedCases() as $group => $items)
                <optgroup label="{{ $group }}">
                    @foreach ($items as $t)
                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        <select wire:model.live="siteFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="0">{{ __('common.all_sites') }}</option>
            @foreach ($sites as $s)
                <option value="{{ $s->id }}">{{ $s->name }}@if (filled($s->address)) — {{ $s->address }}@endif</option>
            @endforeach
        </select>
        <select wire:model.live="roomFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="0">{{ __('common.all_rooms') }}</option>
            @foreach ($filterRooms as $r)
                <option value="{{ $r->id }}">{{ $r->name }}@if ($r->site) ({{ $r->site->name }})@endif</option>
            @endforeach
        </select>
        <select wire:model.live="rackFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="0">{{ __('common.all_racks') }}</option>
            @foreach ($filterRacks as $r)
                <option value="{{ $r->id }}">{{ $r->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">{{ __('common.all_statuses') }}</option>
            @foreach ($statuses as $s)
                <option value="{{ $s->value }}">{{ $s->label() }}</option>
            @endforeach
        </select>
        <select wire:model.live="tagFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="0">{{ __('common.all_tags') }}</option>
            @foreach ($allTags as $tag)
                <option value="{{ $tag->id }}">{{ $tag->name }}</option>
            @endforeach
        </select>
        @if ($search !== '' || $typeFilter !== '' || $siteFilter > 0 || $roomFilter > 0 || $rackFilter > 0 || $statusFilter !== '' || $tagFilter > 0)
            <button wire:click="clearFilters" type="button" class="text-xs text-gray-500 hover:text-gray-700 underline">{{ __('common.reset_filters') }}</button>
        @endif
    </div>

    <div class="bg-white shadow ring-1 ring-black ring-opacity-5 rounded-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('common.name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('common.type') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('equipment.col_vendor_model') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('common.rack') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('equipment.col_interfaces') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                @forelse ($equipment as $eq)
                    <tr wire:key="eq-{{ $eq->id }}">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <span class="inline-flex items-center gap-1.5">
                                <a href="{{ route('equipment.show', $eq) }}" wire:navigate class="text-indigo-700 hover:underline">{{ $eq->name }}</a>
                                @can('update', $eq)
                                    <button type="button"
                                        wire:click="toggleHiddenInTopology({{ $eq->id }})"
                                        title="{{ $eq->hidden_in_topology ? __('equipment.hidden_toggle_title') : __('equipment.visible_toggle_title') }}"
                                        class="text-gray-400 hover:text-indigo-600">
                                        <x-icon name="{{ $eq->hidden_in_topology ? 'eye-slash' : 'eye' }}" class="h-4 w-4" />
                                    </button>
                                @else
                                    <span title="{{ $eq->hidden_in_topology ? __('equipment.hidden_title') : __('equipment.visible_title') }}" class="text-gray-400">
                                        <x-icon name="{{ $eq->hidden_in_topology ? 'eye-slash' : 'eye' }}" class="h-4 w-4" />
                                    </span>
                                @endcan
                            </span>
                            @if ($eq->tags->isNotEmpty())
                                <div class="mt-1"><x-tag-chips :tags="$eq->tags" /></div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700">{{ $eq->type?->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $eq->vendor }} {{ $eq->model }}</td>
                        <td class="px-4 py-3 text-gray-600">
                            @php
                                $room = $eq->rack?->room ?? $eq->room;
                                $site = $room?->site;
                            @endphp
                            @if ($eq->type === \App\Enums\EquipmentType::VirtualMachine && $eq->host)
                                <span class="italic text-gray-500">
                                    {{ __('equipment.vm_on') }} <a href="{{ route('equipment.show', $eq->host) }}" wire:navigate class="text-indigo-700 hover:underline not-italic">{{ $eq->host->name }}</a>
                                </span>
                            @elseif ($eq->rack)
                                {{ $eq->rack->name }}@if ($eq->mounted) · U{{ $eq->position_u_start }}–{{ $eq->position_u_start + $eq->position_u_height - 1 }}@endif
                            @else
                                <span class="italic text-gray-500">{{ __('equipment.not_rack_mounted') }}</span>
                            @endif
                            @if ($room)
                                <span class="block text-xs text-gray-400">{{ $room->name }}</span>
                            @endif
                            @if ($site)
                                <span class="block text-xs text-gray-400">
                                    {{ $site->name }}@if ($site->address) · {{ $site->address }}@endif
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $eq->interfaces_count }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            @can('update', $eq)
                                <button wire:click="openEdit({{ $eq->id }})" class="text-indigo-600 hover:text-indigo-800"><x-icon name="pencil" class="h-4 w-4 inline" /></button>
                            @endcan
                            @can('create', App\Models\Equipment::class)
                                <button wire:click="openDuplicate({{ $eq->id }})" title="{{ __('common.duplicate') }}" class="text-gray-500 hover:text-gray-700"><x-icon name="document-duplicate" class="h-4 w-4 inline" /></button>
                            @endcan
                            @can('delete', $eq)
                                <button wire:click="delete({{ $eq->id }})" wire:confirm="{{ __('equipment.delete_confirm', ['name' => $eq->name]) }}" class="text-red-600 hover:text-red-800"><x-icon name="trash" class="h-4 w-4 inline" /></button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('equipment.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $equipment->links() }}</div>

    @if ($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4 overflow-y-auto">
            <div class="bg-white rounded-md shadow-lg w-full max-w-2xl p-6 my-8">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('equipment.form_edit') : ($duplicatingFromId ? __('equipment.form_duplicate') : __('equipment.new')) }}</h2>
                @if ($duplicatingFromId)
                    <p class="-mt-2 mb-4 text-sm text-gray-500">{{ __('equipment.duplicate_note', ['count' => $duplicatingInterfaceCount]) }}</p>
                @endif
                <form wire:submit="save" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('common.name') }}</label>
                            <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('common.type') }}</label>
                            <select wire:model.live="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                @foreach (\App\Enums\EquipmentType::groupedCases() as $group => $items)
                                    <optgroup label="{{ $group }}">
                                        @foreach ($items as $t)
                                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error('type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('common.vendor') }}</label>
                            <input type="text" wire:model="vendor" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('common.model') }}</label>
                            <input type="text" wire:model="modelName" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('equipment.label_serial') }}</label>
                            <input type="text" wire:model="serial" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('common.status') }}</label>
                            <select wire:model="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                @foreach ($statuses as $s)
                                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('equipment.label_firmware') }}</label>
                            <input type="text" wire:model="firmware" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            @error('firmware')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('equipment.label_asset_tag') }}</label>
                            <input type="text" wire:model="assetTag" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            @error('assetTag')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('equipment.label_mgmt_ip') }}</label>
                            <input type="text" wire:model="managementIp" placeholder="{{ __('equipment.mgmt_ip_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            @error('managementIp')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    @if ($type === 'hypervisor')
                        <div class="border-t pt-6 mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('equipment.label_hypervisor_vendor') }}</label>
                                <select wire:model="hypervisorVendor" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">—</option>
                                    @foreach ($hypervisorVendors as $v)
                                        <option value="{{ $v->value }}">{{ $v->label() }}</option>
                                    @endforeach
                                </select>
                                @error('hypervisorVendor')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    @endif

                    @if ($type === 'virtual_machine')
                        <div class="border-t pt-6 mt-2 space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('equipment.label_host') }}</label>
                                <select wire:model="hostEquipmentId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">{{ __('common.select_placeholder') }}</option>
                                    @foreach ($hypervisorOptions as $h)
                                        <option value="{{ $h->id }}">{{ $h->name }}</option>
                                    @endforeach
                                </select>
                                @error('hostEquipmentId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">vCPU</label>
                                    <input type="number" min="1" wire:model="vcpu" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                    @error('vcpu')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('equipment.label_ram') }}</label>
                                    <input type="number" min="1" wire:model="ramMb" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                    @error('ramMb')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('equipment.label_disk') }}</label>
                                    <input type="number" min="1" wire:model="diskGb" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                    @error('diskGb')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('equipment.label_guest_os') }}</label>
                                    <input type="text" wire:model="guestOs" placeholder="{{ __('equipment.guest_os_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                    @error('guestOs')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <p class="text-xs text-gray-500">{{ __('equipment.vm_help') }}</p>
                        </div>
                    @endif

                    <div class="border-t pt-6 mt-2" @if ($type === 'virtual_machine') style="display:none" @endif>
                        <div class="flex items-center gap-x-6 gap-y-5 flex-wrap">
                            <label class="inline-flex items-center gap-x-2 text-sm">
                                <input type="checkbox" wire:model.live="mounted" class="rounded border-gray-300 text-indigo-600" />
                                {{ __('equipment.label_mounted') }}
                            </label>
                            <label class="inline-flex items-center gap-x-2 text-sm">
                                <input type="checkbox" wire:model="locked" class="rounded border-gray-300 text-indigo-600" />
                                {{ __('equipment.label_locked') }}
                            </label>
                            <label class="inline-flex items-center gap-x-2 text-sm">
                                <input type="checkbox" wire:model="hiddenInTopology" class="rounded border-gray-300 text-indigo-600" />
                                {{ __('equipment.label_hidden_topology') }}
                            </label>
                            @if ($mounted)
                                <label class="inline-flex items-center gap-x-2 text-sm">
                                    <input type="checkbox" wire:model.live="onTop" class="rounded border-gray-300 text-indigo-600" />
                                    {{ __('equipment.label_on_top') }}
                                </label>
                            @endif
                        </div>

                        @if ($mounted)
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('common.rack') }}</label>
                                    <select wire:model="rackId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        <option value="">—</option>
                                        @foreach ($racks as $r)
                                            <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->height_units }}U)</option>
                                        @endforeach
                                    </select>
                                    @error('rackId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('equipment.label_side') }}</label>
                                    <select wire:model="positionOrient" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        <option value="front">{{ __('equipment.side_front') }}</option>
                                        <option value="rear">{{ __('equipment.side_rear') }}</option>
                                    </select>
                                    @error('positionOrient')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('equipment.label_u_start') }}</label>
                                    <input type="number" min="1" wire:model="positionUStart"
                                           @disabled($onTop)
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100 disabled:text-gray-400" />
                                    @error('positionUStart')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('equipment.label_u_height') }}</label>
                                    <input type="number" min="1" wire:model="positionUHeight"
                                           @disabled($onTop)
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100 disabled:text-gray-400" />
                                    @error('positionUHeight')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        @endif

                        {{-- Locale: editable select when device is unracked; read-only
                             label derived from the rack when device is racked. --}}
                        <div class="mt-3">
                            @if (!$mounted)
                                <label class="block text-sm font-medium text-gray-700">{{ __('common.room') }}</label>
                                <select wire:model="roomId" class="mt-1 block w-full sm:w-1/2 rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">{{ __('common.none_option') }}</option>
                                    @foreach ($rooms as $r)
                                        <option value="{{ $r->id }}">{{ $r->name }} @if ($r->site) ({{ $r->site->name }}) @endif</option>
                                    @endforeach
                                </select>
                                @error('roomId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            @else
                                @php
                                    $derivedRoom = $rackId ? optional($racks->firstWhere('id', (int) $rackId))->room?->name : null;
                                @endphp
                                <p class="text-xs text-gray-500">
                                    {{ __('common.room') }}: <span class="font-medium text-gray-700">{{ $derivedRoom ?? '—' }}</span>
                                    <span class="text-gray-400">{{ __('equipment.room_derived') }}</span>
                                </p>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('common.description') }}</label>
                        <textarea wire:model="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('equipment.label_icon') }}</label>
                        @if ($iconUpload)
                            <div class="mt-1 flex items-center gap-3">
                                <img src="{{ $iconUpload->temporaryUrl() }}" alt="{{ __('common.preview_alt') }}" class="h-16 w-16 object-contain rounded border border-gray-200 bg-gray-50" />
                                <button type="button" wire:click="$set('iconUpload', null)" class="text-xs text-red-600 hover:underline">{{ __('common.cancel_upload') }}</button>
                            </div>
                        @elseif ($existingIconPath)
                            <div class="mt-1 flex items-center gap-3">
                                <img src="/storage/{{ ltrim($existingIconPath, '/') }}" alt="{{ __('common.current_icon_alt') }}" class="h-16 w-16 object-contain rounded border border-gray-200 bg-gray-50" />
                                <button type="button" wire:click="removeIcon" wire:confirm="{{ __('equipment.remove_icon_confirm') }}" class="text-xs text-red-600 hover:underline">{{ __('common.remove') }}</button>
                            </div>
                        @endif
                        <input type="file" accept="image/*" wire:model="iconUpload" class="mt-1 block w-full text-sm" />
                        <p class="text-xs text-gray-500 mt-1">{{ __('equipment.icon_help') }}</p>
                        @error('iconUpload')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        <div wire:loading wire:target="iconUpload" class="text-xs text-indigo-600 mt-1">{{ __('common.uploading') }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.tags') }}</label>
                        <x-tag-selector :tags="$allTags" model="selectedTagIds" />
                        @error('selectedTagIds')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex justify-end gap-x-2 pt-2">
                        <button type="button" wire:click="$set('showForm', false)" class="px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-3 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
