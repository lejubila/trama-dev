@props(['tags'])

@if ($tags->isNotEmpty())
    <span {{ $attributes->merge(['class' => 'inline-flex flex-wrap items-center gap-1.5']) }}>
        @foreach ($tags as $tag)
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium text-white" style="background-color: {{ $tag->color }}">
                {{ $tag->name }}
            </span>
        @endforeach
    </span>
@endif
