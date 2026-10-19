<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Support\Api\TokenAbilities;
use App\Support\Demo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin-only management of API tokens: each token gets its own
 * resource × action abilities and an optional tenant allow-list.
 */
#[Layout('layouts.app')]
class ApiTokens extends Component
{
    public bool $showForm = false;

    /** Token being edited; null while creating. */
    public ?int $editingId = null;

    public string $name = '';

    /** @var list<string> */
    public array $abilities = [];

    /** 'all' = every tenant, 'selected' = only $tenantIds. */
    public string $tenantScope = 'all';

    /** @var list<int|string> */
    public array $tenantIds = [];

    public ?string $expiresAt = null;

    /** @var list<string> Abilities before the current checkbox change (request-local). */
    protected array $previousAbilities = [];

    /** Plain-text token shown ONCE right after creation. */
    public ?string $newPlainToken = null;

    public function mount(): void
    {
        // Tokens are long-lived credentials for the shared demo account.
        abort_if(Demo::enabled(), 403, __('demo.tokens_locked'));
        Gate::authorize('manage-api-tokens');
    }

    public function openCreate(): void
    {
        $this->authorizeTokens();
        $this->resetForm();
        $this->abilities = TokenAbilities::readOnly();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorizeTokens();
        $token = $this->findToken($id);

        $this->resetForm();
        $this->editingId = $token->id;
        $this->name = $token->name;
        $this->abilities = TokenAbilities::normalize((array) $token->abilities);
        $this->tenantScope = $token->tenant_ids === null ? 'all' : 'selected';
        $this->tenantIds = array_map('intval', $token->tenant_ids ?? []);
        $this->expiresAt = $token->expires_at?->format('Y-m-d');
        $this->showForm = true;
    }

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

    public function save(): void
    {
        $this->authorizeTokens();

        $data = $this->validate([
            'name' => 'required|string|max:80',
            'abilities' => 'array|min:1',
            'abilities.*' => ['string', Rule::in(TokenAbilities::grantable())],
            'tenantScope' => 'required|in:all,selected',
            'tenantIds' => 'array|required_if:tenantScope,selected',
            'tenantIds.*' => 'integer|exists:tenants,id',
            'expiresAt' => $this->editingId === null ? 'nullable|date|after:today' : 'nullable|date',
        ], [
            'abilities.min' => __('settings.err_no_abilities'),
            'tenantIds.required_if' => __('settings.err_no_tenants'),
        ]);

        $abilities = TokenAbilities::normalize($data['abilities']);
        $tenantIds = $data['tenantScope'] === 'selected'
            ? array_values(array_unique(array_map('intval', $data['tenantIds'])))
            : null;
        $expiresAt = filled($data['expiresAt']) ? Carbon::parse($data['expiresAt'])->endOfDay() : null;

        if ($this->editingId !== null) {
            $this->findToken($this->editingId)->forceFill([
                'name' => $data['name'],
                'abilities' => $abilities,
                'tenant_ids' => $tenantIds,
                'expires_at' => $expiresAt,
            ])->save();

            $this->dispatch('toast', type: 'success', message: __('settings.toast_token_updated'));
        } else {
            $token = auth()->user()->createToken($data['name'], $abilities, $expiresAt);
            $token->accessToken->forceFill(['tenant_ids' => $tenantIds])->save();

            $this->newPlainToken = $token->plainTextToken;
            $this->dispatch('toast', type: 'success', message: __('settings.toast_token_created'));
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function revoke(int $id): void
    {
        $this->authorizeTokens();
        $this->findToken($id)->delete();
        $this->dispatch('toast', type: 'success', message: __('settings.toast_token_revoked'));
    }

    public function render(): View
    {
        $tenants = Tenant::query()->orderBy('name')->get(['id', 'name']);
        $tenantNames = $tenants->pluck('name', 'id');
        /** @var Collection<int, PersonalAccessToken> $tokens */
        $tokens = auth()->user()->tokens()->orderByDesc('id')->get();

        return view('livewire.settings.api-tokens', [
            'tokens' => $tokens,
            'summaries' => $tokens->mapWithKeys(fn (PersonalAccessToken $t): array => [$t->id => [
                'abilities' => $this->abilitiesLabel((array) $t->abilities),
                'badges' => array_values(array_filter(
                    TokenAbilities::SPECIAL,
                    fn (string $label, string $ability): bool => in_array($ability, (array) $t->abilities, true),
                    ARRAY_FILTER_USE_BOTH,
                )),
                'tenants' => $t->tenant_ids === null
                    ? __('settings.tenants_all')
                    : collect($t->tenant_ids)->map(fn ($id) => $tenantNames[$id] ?? "#{$id}")->implode(', '),
            ]]),
            'resources' => TokenAbilities::RESOURCES,
            'actions' => TokenAbilities::ACTIONS,
            'tenants' => $tenants,
            'special' => TokenAbilities::SPECIAL,
        ]);
    }

    /**
     * @param  array<int, string>  $abilities
     */
    private function abilitiesLabel(array $abilities): string
    {
        $granted = array_values(array_intersect(TokenAbilities::all(), $abilities));

        return match (true) {
            in_array('*', $abilities, true) || $granted === TokenAbilities::all() => __('settings.abilities_all'),
            $granted === TokenAbilities::readOnly() => __('settings.abilities_read_only'),
            default => trans_choice('settings.abilities_count', count($granted), ['count' => count($granted)]),
        };
    }

    private function authorizeTokens(): void
    {
        abort_if(Demo::enabled(), 403, __('demo.tokens_locked'));
        Gate::authorize('manage-api-tokens');
    }

    private function findToken(int $id): PersonalAccessToken
    {
        $token = auth()->user()->tokens()->whereKey($id)->first();
        abort_unless($token instanceof PersonalAccessToken, 404);

        return $token;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'abilities', 'tenantScope', 'tenantIds', 'expiresAt']);
        $this->resetValidation();
    }
}
