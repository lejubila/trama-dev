<div>
@if ($tenants->isEmpty())
    <a
        href="{{ route('tenants.index', ['new' => 1]) }}"
        wire:navigate
        class="inline-flex items-center gap-x-1.5 rounded-md border border-indigo-300 dark:border-indigo-700 bg-indigo-50 dark:bg-indigo-500/20 px-3 py-1.5 text-sm font-medium text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-500/30"
    >
        + Crea il tuo primo cliente
    </a>
@else
    <div x-data="{ open: false }" @click.outside="open = false" class="relative">
        <button
            type="button"
            @click="open = !open"
            class="inline-flex items-center gap-x-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
        >
            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6h1.5m-1.5 3h1.5m-1.5 3h1.5M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
            </svg>
            <span>
                {{ $tenants->firstWhere('id', $currentTenantId)?->name ?? 'Seleziona cliente' }}
            </span>
            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <div
            x-show="open"
            x-transition
            x-cloak
            class="absolute right-0 z-50 mt-2 w-64 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 dark:bg-gray-800"
        >
            <div class="max-h-72 overflow-y-auto py-1" role="menu">
                @foreach ($tenants as $tenant)
                    <form method="POST" action="{{ route('tenant.switch', $tenant) }}">
                        @csrf
                        <button
                            type="submit"
                            class="flex w-full items-center justify-between px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 {{ $tenant->id === $currentTenantId ? 'font-semibold text-indigo-600 dark:text-indigo-400' : 'text-gray-700 dark:text-gray-200' }}"
                        >
                            <span>{{ $tenant->name }}</span>
                            @if ($tenant->id === $currentTenantId)
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            @endif
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    </div>
@endif
</div>
