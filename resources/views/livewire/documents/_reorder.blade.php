{{-- Up/down reorder buttons. Vars: $id, $method, $canUp, $canDown --}}
<span class="inline-flex flex-col leading-none -my-1">
    <button type="button" wire:click="{{ $method }}({{ $id }}, -1)" @disabled(! $canUp)
            class="text-[10px] text-gray-400 hover:text-indigo-600 disabled:opacity-25 disabled:hover:text-gray-400"
            title="Sposta su">▲</button>
    <button type="button" wire:click="{{ $method }}({{ $id }}, 1)" @disabled(! $canDown)
            class="text-[10px] text-gray-400 hover:text-indigo-600 disabled:opacity-25 disabled:hover:text-gray-400"
            title="Sposta giù">▼</button>
</span>
