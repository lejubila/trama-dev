<div>
    <x-page-header title="Notifiche" subtitle="Storico delle notifiche personali">
        <button wire:click="markAllRead" class="text-sm text-indigo-700 dark:text-indigo-300 hover:underline">Segna tutte come lette</button>
    </x-page-header>

    <div class="bg-white dark:bg-slate-800 shadow ring-1 ring-black ring-opacity-5 dark:ring-slate-600 rounded-md overflow-hidden">
        <ul class="divide-y divide-gray-200 dark:divide-slate-700">
            @forelse ($notifications as $n)
                <li wire:key="n-{{ $n->id }}" class="px-4 py-3 {{ $n->read_at ? '' : 'bg-indigo-50/40 dark:bg-indigo-500/10' }}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ data_get($n->data, 'title', 'Notifica') }}</div>
                            <div class="text-xs text-gray-500 dark:text-slate-400">{{ data_get($n->data, 'message') }}</div>
                            <div class="text-[10px] text-gray-400 dark:text-slate-500 mt-1">{{ $n->created_at?->format('Y-m-d H:i') }}</div>
                        </div>
                        <div class="flex items-center gap-x-2 text-xs">
                            @if (data_get($n->data, 'url'))
                                <a href="{{ data_get($n->data, 'url') }}" wire:navigate class="text-indigo-700 dark:text-indigo-300 hover:underline">Apri →</a>
                            @endif
                            @unless ($n->read_at)
                                <button wire:click="markRead('{{ $n->id }}')" class="text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200">Segna letta</button>
                            @endunless
                        </div>
                    </div>
                </li>
            @empty
                <li class="px-4 py-6 text-center text-sm text-gray-500 dark:text-slate-400">Nessuna notifica.</li>
            @endforelse
        </ul>
    </div>

    <div class="mt-4">{{ $notifications->links() }}</div>
</div>
