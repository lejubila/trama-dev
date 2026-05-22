<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Support\Tenancy\TenantContext;

/**
 * Persists a component's filter properties in the user session so the last
 * filter chosen on each list screen is restored on the next visit.
 *
 * The URL (#[Url]) still wins when present: explicit query-string values are
 * never overridden by the session, and they are written back to the session
 * so a shared link also updates the remembered state. A component opts in by
 * declaring which public properties are filters via {@see rememberedFilters()}.
 */
trait RemembersFilters
{
    public function mountRemembersFilters(): void
    {
        $stored = session()->get($this->filtersSessionKey(), []);
        $query = request()->query();

        foreach ($this->rememberedFilters() as $prop) {
            // A value present in the URL takes precedence over the session.
            if (! array_key_exists($prop, $query) && array_key_exists($prop, $stored)) {
                $this->{$prop} = $stored[$prop];
            }
        }
    }

    public function updatedRemembersFilters(string $name): void
    {
        $base = explode('.', $name)[0];

        if (in_array($base, $this->rememberedFilters(), true)) {
            $this->persistFilters();
        }
    }

    /**
     * Snapshot the current filter values to the session. Call this after a
     * programmatic reset (e.g. clearFilters) since reset() skips updated hooks.
     */
    protected function persistFilters(): void
    {
        $data = [];

        foreach ($this->rememberedFilters() as $prop) {
            $data[$prop] = $this->{$prop};
        }

        session()->put($this->filtersSessionKey(), $data);
    }

    protected function filtersSessionKey(): string
    {
        return 'filters.'.static::class.'.'.(TenantContext::id() ?? 'global');
    }

    /**
     * Public properties whose values should persist across visits.
     *
     * @return array<int, string>
     */
    abstract protected function rememberedFilters(): array;
}
