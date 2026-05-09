<div x-data @click.outside="$wire.set('open', false)" class="relative">
    <button
        type="button"
        wire:click="toggle"
        class="relative inline-flex items-center rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 p-1.5 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700"
        aria-label="Notifiche"
    >
        <x-icon name="clock" class="h-5 w-5" />
        @if ($unread > 0)
            <span class="absolute -top-1 -right-1 inline-flex items-center justify-center rounded-full bg-red-600 text-white text-[10px] font-bold min-w-[18px] h-[18px] px-1">
                {{ $unread > 9 ? '9+' : $unread }}
            </span>
        @endif
    </button>

    @if ($open)
        <div class="absolute right-0 z-50 mt-2 w-80 rounded-md bg-white dark:bg-slate-800 shadow-lg ring-1 ring-black ring-opacity-5 dark:ring-slate-600">
            <div class="flex items-center justify-between px-3 py-2 border-b border-gray-100 dark:border-slate-700">
                <span class="text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400 font-semibold">Notifiche</span>
                @if ($unread > 0)
                    <button wire:click="markAllRead" class="text-xs text-indigo-700 dark:text-indigo-300 hover:underline">Segna tutte come lette</button>
                @endif
            </div>
            <ul class="max-h-80 overflow-y-auto">
                @forelse ($latest as $n)
                    <li wire:key="n-{{ $n->id }}" class="border-b border-gray-100 dark:border-slate-700 last:border-0">
                        <a href="{{ data_get($n->data, 'url', route('notifications.index')) }}"
                           wire:click="markRead('{{ $n->id }}')"
                           class="block px-3 py-2 hover:bg-gray-100 dark:hover:bg-slate-700 {{ $n->read_at ? '' : 'bg-indigo-50/40 dark:bg-indigo-500/10' }}">
                            <div class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ data_get($n->data, 'title', 'Notifica') }}</div>
                            <div class="text-xs text-gray-500 dark:text-slate-400">{{ data_get($n->data, 'message') }}</div>
                            <div class="text-[10px] text-gray-400 dark:text-slate-500 mt-1">{{ $n->created_at?->diffForHumans() }}</div>
                        </a>
                    </li>
                @empty
                    <li class="px-3 py-6 text-center text-sm text-gray-500 dark:text-slate-400">Nessuna notifica.</li>
                @endforelse
            </ul>
            <div class="px-3 py-2 border-t border-gray-100 dark:border-slate-700 text-right">
                <a href="{{ route('notifications.index') }}" wire:navigate class="text-xs text-indigo-700 dark:text-indigo-300 hover:underline">Vedi tutte →</a>
            </div>
        </div>
    @endif
</div>
