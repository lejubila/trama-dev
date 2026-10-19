<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Support\Api\TokenAbilities;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * The resource × action matrix and tenant allow-list editor shared by the
 * API token form and the OAuth consent page (view: <x-token-permissions>).
 */
trait EditsTokenPermissions
{
    /** @var list<string> */
    public array $abilities = [];

    /** 'all' = every tenant, 'selected' = only $tenantIds. */
    public string $tenantScope = 'all';

    /** @var list<int|string> */
    public array $tenantIds = [];

    /** @var list<string> Abilities before the current checkbox change (request-local). */
    protected array $previousAbilities = [];

    public function preset(string $preset): void
    {
        $this->abilities = match ($preset) {
            'read' => TokenAbilities::readOnly(),
            'all' => TokenAbilities::all(),
            default => [],
        };
    }

    /**
     * Ticking a write action also ticks "read" on the same resource;
     * unticking "read" drops the write actions that depend on it.
     */
    public function updatingAbilities(): void
    {
        $this->previousAbilities = $this->abilities;
    }

    public function updatedAbilities(): void
    {
        $readDropped = array_filter(
            array_keys(TokenAbilities::RESOURCES),
            fn (string $r): bool => in_array(TokenAbilities::make($r, TokenAbilities::READ), $this->previousAbilities, true)
                && ! in_array(TokenAbilities::make($r, TokenAbilities::READ), $this->abilities, true),
        );

        $this->abilities = TokenAbilities::normalize(array_filter(
            $this->abilities,
            fn (string $a): bool => ! in_array(explode(':', $a, 2)[0], $readDropped, true),
        ));
    }

    /**
     * @param  list<string>  $grantable
     * @return array<string, mixed>
     */
    protected function permissionRules(array $grantable): array
    {
        return [
            'abilities' => 'array|min:1',
            'abilities.*' => ['string', Rule::in($grantable)],
            'tenantScope' => 'required|in:all,selected',
            'tenantIds' => 'array|required_if:tenantScope,selected',
            'tenantIds.*' => 'integer|exists:tenants,id',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function permissionMessages(): array
    {
        return [
            'abilities.min' => __('settings.err_no_abilities'),
            'tenantIds.required_if' => __('settings.err_no_tenants'),
        ];
    }

    /**
     * Normalised abilities and tenant allow-list (null = all) from the form.
     *
     * @return array{0: list<string>, 1: list<int>|null}
     */
    protected function chosenPermissions(): array
    {
        $tenantIds = $this->tenantScope === 'selected'
            ? array_values(array_unique(array_map('intval', $this->tenantIds)))
            : null;

        return [TokenAbilities::normalize($this->abilities), $tenantIds];
    }

    /**
     * @param  array<int, string>  $abilities
     * @param  list<int|string>|null  $tenantIds
     */
    protected function loadPermissions(array $abilities, ?array $tenantIds): void
    {
        $this->abilities = TokenAbilities::normalize($abilities);
        $this->tenantScope = $tenantIds === null ? 'all' : 'selected';
        $this->tenantIds = array_map('intval', $tenantIds ?? []);
    }

    /**
     * @param  array<int, string>  $abilities
     */
    protected function abilitiesLabel(array $abilities): string
    {
        $granted = array_values(array_intersect(TokenAbilities::all(), $abilities));

        return match (true) {
            in_array('*', $abilities, true) || $granted === TokenAbilities::all() => __('settings.abilities_all'),
            $granted === TokenAbilities::readOnly() => __('settings.abilities_read_only'),
            default => trans_choice('settings.abilities_count', count($granted), ['count' => count($granted)]),
        };
    }

    /**
     * @param  array<int, string>  $abilities
     * @return list<string> Label keys of the special abilities granted.
     */
    protected function specialBadges(array $abilities): array
    {
        return array_values(array_filter(
            TokenAbilities::SPECIAL,
            fn (string $label, string $ability): bool => in_array($ability, $abilities, true),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * @param  array<int, int|string>|null  $tenantIds
     * @param  Collection<int|string, string>  $tenantNames  id → name
     */
    protected function tenantsLabel(?array $tenantIds, Collection $tenantNames): string
    {
        return $tenantIds === null
            ? __('settings.tenants_all')
            : collect($tenantIds)->map(fn ($id) => $tenantNames[$id] ?? "#{$id}")->implode(', ');
    }
}
