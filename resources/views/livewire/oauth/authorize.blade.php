<div class="py-2">
    @if ($fatal)
        <h1 class="text-lg font-semibold text-gray-900">{{ __('oauth.title_error') }}</h1>
        <p class="mt-2 text-sm text-gray-600">{{ $fatal }}</p>
    @else
        <h1 class="text-lg font-semibold text-gray-900">
            {!! __('oauth.title', ['client' => '<span class="text-indigo-700">'.e($clientName).'</span>']) !!}
        </h1>
        <p class="mt-1 text-sm text-gray-600">
            {{ __('oauth.subtitle', ['user' => auth()->user()?->name, 'host' => $redirectHost]) }}
        </p>

        @if ($denied)
            <div class="mt-4 rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900">
                {{ __('oauth.denied') }}
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" wire:click="deny" class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md">{{ __('common.cancel') }}</button>
            </div>
        @else
            <p class="mt-4 text-sm text-gray-700">{{ __('oauth.explain') }}</p>

            <form wire:submit="approve" class="mt-4 space-y-5">
                <x-token-permissions :resources="$resources" :actions="$actions" :special="$special" :tenants="$tenants" :tenant-scope="$tenantScope" />

                <p class="text-xs text-gray-500">{{ __('oauth.revoke_hint') }}</p>

                <div class="flex justify-end gap-x-2">
                    <button type="button" wire:click="deny" class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md">{{ __('oauth.deny') }}</button>
                    <button type="submit" class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">{{ __('oauth.approve') }}</button>
                </div>
            </form>
        @endif
    @endif
</div>
