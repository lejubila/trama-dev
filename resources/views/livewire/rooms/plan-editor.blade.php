@php
    $scale = \App\Livewire\Rooms\Map::SCALE;
    $defaultW = \App\Livewire\Rooms\Map::DEFAULT_WIDTH_M;
    $defaultD = \App\Livewire\Rooms\Map::DEFAULT_DEPTH_M;
    $widthM = $room->width_m !== null ? (float) $room->width_m : $defaultW;
    $depthM = $room->depth_m !== null ? (float) $room->depth_m : $defaultD;
    $vbW = $widthM * $scale;
    $vbH = $depthM * $scale;
    $floorPlanUrl = $room->floor_plan_path ? '/storage/'.ltrim($room->floor_plan_path, '/') : null;
    $initialJson = json_encode($drawing, JSON_UNESCAPED_UNICODE);
@endphp
<div class="p-6 max-w-7xl mx-auto" x-data="roomPlanEditor" x-init="init($el)">
    <div class="flex items-center justify-between mb-3">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-slate-100">{{ __('rooms.plan_editor_title') }}</h1>
            <p class="text-xs text-gray-500 dark:text-slate-400">
                {{ $room->site?->name }} — {{ $room->name }} ·
                {{ __('rooms.map_dimensions', ['w' => number_format($widthM, 2), 'h' => number_format($depthM, 2)]) }}
            </p>
        </div>
        <a href="{{ route('rooms.show', $room) }}" class="text-sm text-indigo-600 hover:underline">← {{ __('rooms.plan_editor_back') }}</a>
    </div>

    <p class="text-xs text-gray-600 dark:text-slate-300 mb-2">{{ __('rooms.plan_editor_subtitle') }}</p>

    <div class="sticky top-0 z-30 bg-gray-100 dark:bg-slate-900 -mx-6 px-6 pt-2 pb-2">
    {{-- Toolbar — Row 1: tools (left) + actions (right) --}}
    <div class="flex flex-wrap items-center gap-2 mb-2 p-2 bg-white dark:bg-slate-800 rounded-md border border-gray-200 dark:border-slate-700 text-sm">
        @php
            $tools = [
                ['wall',   'rooms.plan_editor_tool_wall'],
                ['door',   'rooms.plan_editor_tool_door'],
                ['window', 'rooms.plan_editor_tool_window'],
                ['label',  'rooms.plan_editor_tool_label'],
                ['erase',  'rooms.plan_editor_tool_erase'],
            ];
        @endphp
        @foreach ($tools as [$m, $k])
            <button type="button" @click="setMode('{{ $m }}')"
                :class="mode === '{{ $m }}' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                class="px-2 py-1 rounded text-xs font-medium">{{ __($k) }}</button>
        @endforeach

        <div class="ml-auto flex items-center gap-2">
            <button type="button" @click="showDevices = !showDevices"
                :class="showDevices ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700'"
                class="px-2 py-1 text-xs rounded hover:bg-indigo-200">
                <span x-show="showDevices">{{ __('rooms.plan_editor_hide_devices') }}</span>
                <span x-show="!showDevices">{{ __('rooms.plan_editor_show_devices') }}</span>
            </button>
            <button type="button" @click="undo()" class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200 text-gray-700">{{ __('rooms.plan_editor_undo') }}</button>
            <button type="button" @click="redo()" class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200 text-gray-700">{{ __('rooms.plan_editor_redo') }}</button>
            <button type="button" @click="clearAll()" class="px-2 py-1 text-xs rounded bg-red-50 hover:bg-red-100 text-red-700">{{ __('rooms.plan_editor_clear') }}</button>
            <button type="button" @click="save()" class="px-3 py-1 rounded bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-500">{{ __('rooms.plan_editor_save') }}</button>
        </div>
    </div>

    {{-- Toolbar — Row 2: always visible. Left side = mode-specific options;
         right side = mode-specific hint (in select mode it carries the
         "Modalità: selezione" info). --}}
    <div class="flex flex-wrap items-center gap-3 mb-2 p-2 bg-gray-50 dark:bg-slate-900/50 rounded-md border border-gray-200 dark:border-slate-700 text-sm min-h-[40px]">
        <template x-if="mode === 'wall'">
            <div class="flex flex-wrap items-center gap-3">
                <label class="inline-flex items-center gap-1 text-xs text-gray-700 dark:text-slate-300">
                    <input type="checkbox" x-model="snapAxis" class="rounded border-gray-300 text-indigo-600" />
                    {{ __('rooms.plan_editor_axis_snap') }}
                </label>
                <label class="inline-flex items-center gap-1 text-xs text-gray-700 dark:text-slate-300">
                    {{ __('rooms.plan_editor_wall_thickness') }}
                    <input type="number" step="0.05" min="0.05" max="1" x-model.number="wallThickness" class="w-16 rounded border-gray-300 text-xs py-0.5" />
                </label>
                <button type="button" x-show="_draftWall && _draftWall.points.length >= 2" @click="commitWall()" class="px-2 py-1 text-xs rounded bg-emerald-600 text-white hover:bg-emerald-500">{{ __('rooms.plan_editor_finish_wall') }}</button>
            </div>
        </template>
        <template x-if="mode === 'door'">
            <label class="inline-flex items-center gap-1 text-xs text-gray-700 dark:text-slate-300">
                {{ __('rooms.plan_editor_door_width') }}
                <input type="number" step="0.1" min="0.4" max="3" x-model.number="doorWidth" class="w-16 rounded border-gray-300 text-xs py-0.5" />
            </label>
        </template>
        <template x-if="mode === 'select' && selectedKind === 'door'">
            <button type="button" @click="toggleDoorSwing()" class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200 text-gray-700">{{ __('rooms.plan_editor_door_swing') }}</button>
        </template>
        <template x-if="mode === 'window'">
            <label class="inline-flex items-center gap-1 text-xs text-gray-700 dark:text-slate-300">
                {{ __('rooms.plan_editor_window_width') }}
                <input type="number" step="0.1" min="0.2" max="5" x-model.number="windowWidth" class="w-16 rounded border-gray-300 text-xs py-0.5" />
            </label>
        </template>
        <template x-if="mode === 'label'">
            <label class="inline-flex items-center gap-1 text-xs text-gray-700 dark:text-slate-300">
                {{ __('rooms.plan_editor_label_text') }}
                <input type="text" x-model="labelText" maxlength="120" class="w-48 rounded border-gray-300 text-xs py-0.5" />
            </label>
        </template>

        <span class="ml-auto text-[11px] italic text-gray-500 text-right">
            <span x-show="mode === 'select'">{{ __('rooms.plan_editor_mode_select') }}</span>
            <span x-show="mode === 'wall'">{{ __('rooms.plan_editor_hint_wall') }}</span>
            <span x-show="mode === 'door'">{{ __('rooms.plan_editor_hint_door') }}</span>
            <span x-show="mode === 'window'">{{ __('rooms.plan_editor_hint_window') }}</span>
            <span x-show="mode === 'label'">{{ __('rooms.plan_editor_hint_label') }}</span>
            <span x-show="mode === 'erase'">{{ __('rooms.plan_editor_hint_erase') }}</span>
        </span>
    </div>
    </div>

    {{-- Canvas — wire:ignore: Livewire morphdom would otherwise wipe the
         <g class="plan-layer"> contents that Alpine fills client-side on
         every server roundtrip (e.g. after Save). --}}
    <svg
        wire:ignore
        class="plan-editor w-full bg-white dark:bg-slate-900 rounded-md border border-gray-200 dark:border-slate-700 select-none"
        viewBox="0 0 {{ $vbW }} {{ $vbH }}"
        preserveAspectRatio="xMidYMid meet"
        data-scale="{{ $scale }}"
        data-room-w-m="{{ $widthM }}"
        data-room-h-m="{{ $depthM }}"
        data-initial='@json($drawing)'
        xmlns="http://www.w3.org/2000/svg"
        style="touch-action: none; cursor: crosshair;"
    >
        @if ($floorPlanUrl)
            <image href="{{ $floorPlanUrl }}" x="0" y="0" width="{{ $vbW }}" height="{{ $vbH }}" preserveAspectRatio="none" opacity="0.55" />
        @endif

        {{-- 1 m grid --}}
        @for ($i = 1; $i * $scale < $vbW; $i++)
            <line x1="{{ $i * $scale }}" y1="0" x2="{{ $i * $scale }}" y2="{{ $vbH }}" stroke="#e5e7eb" stroke-width="0.5" />
        @endfor
        @for ($i = 1; $i * $scale < $vbH; $i++)
            <line x1="0" y1="{{ $i * $scale }}" x2="{{ $vbW }}" y2="{{ $i * $scale }}" stroke="#e5e7eb" stroke-width="0.5" />
        @endfor

        {{-- Room boundary --}}
        <rect x="0" y="0" width="{{ $vbW }}" height="{{ $vbH }}" fill="none" stroke="#94a3b8" stroke-width="1" stroke-dasharray="6,4" />

        {{-- Device overlay: read-only markers for racks + unracked equipment
             placed in this room. Visibility toggled by the toolbar. --}}
        <g class="device-overlay" x-show="showDevices" style="pointer-events: none;">
            @foreach ($racks as $r)
                @php
                    $px = (float) $r->position_x * $scale;
                    $py = (float) $r->position_y * $scale;
                    $iconUrl = $rackIcons[$r->id] ?? null;
                    $size = (int) ($rackIconSizes[$r->id] ?? \App\Livewire\Rooms\Map::DEFAULT_ICON_SIZE_PX);
                    $half = $size / 2;
                    $fontU = max(7, $size * 0.22);
                @endphp
                <g opacity="0.85">
                    @if ($iconUrl)
                        <image href="{{ $iconUrl }}" x="{{ $px - $half }}" y="{{ $py - $half }}" width="{{ $size }}" height="{{ $size }}" preserveAspectRatio="xMidYMid meet" />
                    @else
                        <rect x="{{ $px - $half }}" y="{{ $py - $half }}" width="{{ $size }}" height="{{ $size }}"
                            rx="2" fill="#e0e7ff" stroke="#4f46e5" stroke-width="1" fill-opacity="0.6" />
                    @endif
                    <text x="{{ $px }}" y="{{ $py + $half + $fontU }}" text-anchor="middle" font-size="{{ $fontU }}" fill="#3730a3" font-weight="600"
                        stroke="#ffffff" stroke-width="{{ $fontU * 0.25 }}" paint-order="stroke">{{ $r->name }}</text>
                </g>
            @endforeach
            @foreach ($equipment as $eq)
                @php
                    $px = (float) $eq->position_x * $scale;
                    $py = (float) $eq->position_y * $scale;
                    $iconUrl = $equipmentIcons[$eq->id] ?? null;
                    $size = (int) ($equipmentIconSizes[$eq->id] ?? \App\Livewire\Rooms\Map::DEFAULT_ICON_SIZE_PX);
                    $half = $size / 2;
                    $fontU = max(7, $size * 0.22);
                @endphp
                <g opacity="0.85">
                    @if ($iconUrl)
                        <image href="{{ $iconUrl }}" x="{{ $px - $half }}" y="{{ $py - $half }}" width="{{ $size }}" height="{{ $size }}" preserveAspectRatio="xMidYMid meet" />
                    @else
                        <circle cx="{{ $px }}" cy="{{ $py }}" r="{{ $half * 0.85 }}" fill="#fef3c7" stroke="#d97706" stroke-width="1" fill-opacity="0.65" />
                    @endif
                    <text x="{{ $px }}" y="{{ $py + $half + $fontU }}" text-anchor="middle" font-size="{{ $fontU }}" fill="#92400e" font-weight="600"
                        stroke="#ffffff" stroke-width="{{ $fontU * 0.25 }}" paint-order="stroke">{{ $eq->name }}</text>
                </g>
            @endforeach
        </g>

        {{-- Persisted shapes (rendered by JS) --}}
        <g class="plan-layer"></g>

        {{-- Draft polyline while drawing (rendered by JS) --}}
        <g class="plan-draft"></g>
    </svg>
</div>
