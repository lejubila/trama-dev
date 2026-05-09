<div
    x-data
    x-on:apply-theme.window="(function (e) {
        var t = e.detail.theme;
        if (t === 'system') {
            t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.classList.toggle('dark', t === 'dark');
        document.documentElement.setAttribute('data-theme', e.detail.theme);
        try { localStorage.setItem('trama-theme', e.detail.theme); } catch (e) {}
    })($event)"
    class="inline-flex items-center rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 p-0.5 text-xs"
>
    @php $modes = ['light' => '☀', 'system' => '🖥', 'dark' => '☾']; @endphp
    @foreach ($modes as $value => $glyph)
        <button
            type="button"
            wire:click="setTheme('{{ $value }}')"
            class="px-2 py-1 rounded {{ $theme === $value
                ? 'bg-indigo-600 text-white'
                : 'text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700' }}"
            aria-label="Tema {{ $value }}"
            title="Tema {{ $value }}"
        >{{ $glyph }}</button>
    @endforeach
</div>
