<div>
    <script type="application/json" id="trama-guide">@json($config)</script>

    {{-- "What do you want to do?" menu, opened from the top bar button. --}}
    <div x-data x-show="$store.guide.menuOpen" x-cloak style="display: none"
         class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:pt-20"
         x-on:keydown.escape.window="$store.guide.menuOpen = false"
         x-on:click.self="$store.guide.menuOpen = false">
        <div class="w-full max-w-xl rounded-lg bg-white dark:bg-slate-800 shadow-xl ring-1 ring-black/5 dark:ring-slate-700">
            <div class="flex items-start justify-between gap-4 border-b border-gray-100 dark:border-slate-700 px-5 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100">{{ __('guide.menu_title') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">{{ __('guide.menu_subtitle') }}</p>
                </div>
                <button type="button" x-on:click="$store.guide.menuOpen = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-slate-200" aria-label="{{ __('guide.close') }}"><x-icon name="x-mark" class="h-5 w-5" /></button>
            </div>

            <div class="p-3">
                <button type="button" x-on:click="$store.guide.startFull()"
                        class="flex w-full items-center gap-3 rounded-md bg-indigo-50 dark:bg-indigo-500/10 px-4 py-3 text-left ring-1 ring-indigo-200 dark:ring-indigo-500/30 hover:bg-indigo-100 dark:hover:bg-indigo-500/20">
                    <x-icon name="question-mark-circle" class="h-6 w-6 shrink-0 text-indigo-600 dark:text-indigo-400" />
                    <span class="flex-1">
                        <span class="block text-sm font-semibold text-indigo-900 dark:text-indigo-200">{{ __('guide.full_title') }}</span>
                        <span class="block text-xs text-indigo-700 dark:text-indigo-300">{{ __('guide.full_description') }}</span>
                    </span>
                    <span class="text-xs text-indigo-600 dark:text-indigo-300">{{ __('guide.minutes', ['count' => array_sum(array_column($tours, 'minutes'))]) }}</span>
                </button>

                <ul class="mt-2 divide-y divide-gray-100 dark:divide-slate-700">
                    @foreach ($tours as $tour)
                        <li>
                            <button type="button" x-on:click="$store.guide.start(@js($tour['id']))"
                                    class="flex w-full items-center gap-3 rounded-md px-4 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-slate-700/60">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-slate-700 text-xs font-semibold text-gray-600 dark:text-slate-300">{{ $loop->iteration }}</span>
                                <span class="flex-1">
                                    <span class="block text-sm font-medium text-gray-900 dark:text-slate-100">{{ $tour['title'] }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-slate-400">{{ $tour['description'] }}</span>
                                </span>
                                <span class="text-xs text-gray-400 dark:text-slate-500">{{ __('guide.minutes', ['count' => $tour['minutes']]) }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>

                @unless ($hasTenantTours)
                    <p class="mt-2 px-4 text-xs text-gray-500 dark:text-slate-400">{{ __('guide.no_tenant') }}</p>
                @endunless
            </div>
        </div>
    </div>

    {{-- First-visit invitation: never blocks the page. --}}
    <div x-data x-show="$store.guide.offerVisible" x-cloak style="display: none" x-transition
         class="fixed bottom-4 right-4 z-40 w-[calc(100%-2rem)] max-w-sm rounded-lg bg-white dark:bg-slate-800 p-4 shadow-xl ring-1 ring-black/10 dark:ring-slate-700">
        <div class="flex items-start gap-3">
            <x-icon name="question-mark-circle" class="h-6 w-6 shrink-0 text-indigo-600 dark:text-indigo-400" />
            <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">{{ __('guide.offer_title') }}</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">{{ __('guide.offer_text') }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" x-on:click="$store.guide.acceptOffer()" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">{{ __('guide.offer_start') }}</button>
                    <button type="button" x-on:click="$store.guide.postponeOffer()" class="rounded-md px-3 py-1.5 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700">{{ __('guide.offer_later') }}</button>
                    <button type="button" x-on:click="$store.guide.dismissOffer()" class="rounded-md px-3 py-1.5 text-sm text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700">{{ __('guide.offer_never') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
