@props(['tags', 'model'])

<div class="flex flex-wrap gap-2" x-data="{ selected: @entangle($model) }">
    @forelse ($tags as $tag)
        @php $isSel = "selected.some(v => String(v) === '{$tag->id}')"; @endphp
        <button
            type="button"
            @click="
                const i = selected.findIndex(v => String(v) === '{{ $tag->id }}');
                i === -1 ? selected.push({{ $tag->id }}) : selected.splice(i, 1);
            "
            :style="{{ $isSel }}
                ? 'background-color: {{ $tag->color }}; border-color: {{ $tag->color }}; color: #fff;'
                : 'border-color: {{ $tag->color }}; color: {{ $tag->color }};'"
            class="inline-flex items-center rounded-md border px-3 py-1 text-xs font-medium cursor-pointer transition"
        >
            {{ $tag->name }}
        </button>
    @empty
        <span class="text-xs text-gray-400">{{ __('Nessun tag definito. Creane nella pagina Tag.') }}</span>
    @endforelse
</div>
