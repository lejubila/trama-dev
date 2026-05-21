<div class="mt-6 bg-white dark:bg-slate-800 shadow ring-1 ring-black/5 rounded-md p-4">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-semibold text-gray-700 dark:text-slate-200">{{ __('racks.photos_heading', ['count' => count($photos)]) }}</h3>
    </div>

    @if ($photos->isEmpty())
        <p class="text-sm text-gray-500 dark:text-slate-400">{{ __('racks.no_photos') }}</p>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            @foreach ($photos as $i => $p)
                <div class="relative group">
                    <button type="button"
                            wire:click="openLightbox({{ $i }})"
                            class="block aspect-square w-full overflow-hidden rounded bg-gray-100 dark:bg-slate-900">
                        <img src="/storage/{{ $p->photo_path }}"
                             alt="{{ $p->caption ?? __('racks.photo_alt') }}"
                             class="w-full h-full object-cover" loading="lazy" />
                    </button>
                    @if ($p->caption)
                        <p class="text-xs mt-1 truncate text-gray-600 dark:text-slate-300" title="{{ $p->caption }}">
                            {{ $p->caption }}
                        </p>
                    @endif
                    @can('update', $rack)
                        <button type="button"
                                wire:click="deletePhoto({{ $p->id }})"
                                wire:confirm="{{ __('racks.delete_photo_confirm') }}"
                                class="absolute top-1 right-1 w-6 h-6 rounded-full bg-black/60 text-white text-sm leading-none flex items-center justify-center opacity-0 group-hover:opacity-100 hover:bg-red-600 transition"
                                title="{{ __('racks.delete') }}">×</button>
                    @endcan
                </div>
            @endforeach
        </div>
    @endif

    @can('update', $rack)
        <form wire:submit="savePhotos" class="mt-4 border-t border-gray-200 dark:border-slate-700 pt-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">{{ __('racks.add_photos') }}</label>
            <input type="file" wire:model="newPhotos" multiple accept="image/*"
                   class="block w-full text-sm text-gray-600 dark:text-slate-300
                          file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0
                          file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700
                          hover:file:bg-indigo-100" />
            @error('newPhotos') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            @error('newPhotos.*') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            <div class="mt-2 flex items-center gap-2">
                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="savePhotos,newPhotos"
                        @disabled(empty($newPhotos))
                        class="px-3 py-1.5 text-sm rounded bg-indigo-600 text-white disabled:opacity-50">
                    {{ __('racks.upload') }}
                </button>
                <span wire:loading wire:target="newPhotos" class="text-xs text-gray-500 dark:text-slate-400">{{ __('racks.uploading') }}</span>
                <span wire:loading wire:target="savePhotos" class="text-xs text-gray-500 dark:text-slate-400">{{ __('racks.saving') }}</span>
                <span class="text-xs text-gray-400">{{ __('racks.max_files') }}</span>
            </div>
        </form>
    @endcan

    {{-- Lightbox overlay --}}
    @if ($lightboxIndex >= 0 && isset($photos[$lightboxIndex]))
        @php $cur = $photos[$lightboxIndex]; @endphp
        <div x-data
             x-on:keydown.escape.window="$wire.closeLightbox()"
             x-on:keydown.arrow-left.window="$wire.prev()"
             x-on:keydown.arrow-right.window="$wire.next()"
             class="fixed inset-0 z-50 bg-black/85 flex items-center justify-center p-4"
             x-on:click.self="$wire.closeLightbox()">
            @if (count($photos) > 1)
                <button type="button" wire:click="prev"
                        class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-4xl px-3 hover:text-indigo-300"
                        title="{{ __('racks.prev') }}">‹</button>
                <button type="button" wire:click="next"
                        class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-4xl px-3 hover:text-indigo-300"
                        title="{{ __('racks.next') }}">›</button>
            @endif
            <button type="button" wire:click="closeLightbox"
                    class="absolute top-4 right-4 text-white text-2xl px-3 hover:text-indigo-300"
                    title="{{ __('racks.close') }}">✕</button>

            <div class="max-w-5xl max-h-[90vh] flex flex-col items-center" x-on:click.stop>
                <div class="relative max-w-5xl max-h-[80vh] overflow-hidden select-none touch-none"
                     x-data="rackPhotoZoom()"
                     x-on:wheel.prevent="onWheel($event)"
                     x-on:mousedown="onMouseDown($event)"
                     x-on:mousemove.window="onMouseMove($event)"
                     x-on:mouseup.window="onMouseUp()"
                     x-on:dblclick="onDblClick($event)"
                     x-on:touchstart.passive="onTouchStart($event)"
                     x-on:touchmove="onTouchMove($event)"
                     x-on:touchend="onTouchEnd()"
                     :class="scale > 1 ? (dragging ? 'cursor-grabbing' : 'cursor-grab') : 'cursor-zoom-in'">
                    <img src="/storage/{{ $cur->photo_path }}"
                         alt="{{ $cur->caption ?? __('racks.photo_alt') }}"
                         draggable="false"
                         :style="transformStyle"
                         class="max-w-full max-h-[80vh] object-contain rounded shadow-lg pointer-events-none" />

                    <div x-show="scale !== 1" x-cloak
                         class="absolute top-2 left-2 flex items-center gap-2 bg-black/60 text-white text-xs rounded px-2 py-1">
                        <span x-text="Math.round(scale * 100) + '%'"></span>
                        <button type="button" x-on:click.stop="reset()" class="underline hover:text-indigo-300">{{ __('racks.reset') }}</button>
                    </div>
                </div>

                <div class="mt-3 text-center">
                    @can('update', $rack)
                        @if ($editingId === $cur->id)
                            <div class="flex items-center gap-2">
                                <input type="text"
                                       wire:model="captionDraft"
                                       wire:keydown.enter="saveCaption"
                                       maxlength="500"
                                       placeholder="{{ __('racks.caption_placeholder') }}"
                                       class="rounded text-sm text-gray-900 px-2 py-1" autofocus />
                                <button type="button" wire:click="saveCaption"
                                        class="px-2 py-1 text-xs bg-indigo-600 text-white rounded">OK</button>
                                <button type="button" wire:click="cancelEditCaption"
                                        class="px-2 py-1 text-xs bg-white text-gray-700 rounded border">{{ __('common.cancel') }}</button>
                            </div>
                        @else
                            <button type="button"
                                    wire:click="startEditCaption({{ $cur->id }})"
                                    class="text-sm text-white/80 hover:text-white underline">
                                {{ $cur->caption ?: __('racks.add_caption') }}
                            </button>
                        @endif
                    @else
                        @if ($cur->caption)
                            <p class="text-sm text-white/90">{{ $cur->caption }}</p>
                        @endif
                    @endcan
                    <p class="text-xs text-white/50 mt-1">{{ $lightboxIndex + 1 }} / {{ count($photos) }}</p>
                </div>
            </div>
        </div>
    @endif
</div>
