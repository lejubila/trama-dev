<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Livewire\Concerns\EditsTokenPermissions;
use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Support\Api\TokenAbilities;
use App\Support\Demo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin-only management of API tokens: each token gets its own
 * resource × action abilities and an optional tenant allow-list.
 */
#[Layout('layouts.app')]
class ApiTokens extends Component
{
    use EditsTokenPermissions;

    public bool $showForm = false;

    /** Token being edited; null while creating. */
    public ?int $editingId = null;

    public string $name = '';

    public ?string $expiresAt = null;

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
        $this->loadPermissions((array) $token->abilities, $token->tenant_ids);
        $this->expiresAt = $token->expires_at?->format('Y-m-d');
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeTokens();

        $data = $this->validate([
            'name' => 'required|string|max:80',
            ...$this->permissionRules(TokenAbilities::grantable()),
            'expiresAt' => $this->editingId === null ? 'nullable|date|after:today' : 'nullable|date',
        ], $this->permissionMessages());

        [$abilities, $tenantIds] = $this->chosenPermissions();
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
                'badges' => $this->specialBadges((array) $t->abilities),
                'tenants' => $this->tenantsLabel($t->tenant_ids, $tenantNames),
            ]]),
            'resources' => TokenAbilities::RESOURCES,
            'actions' => TokenAbilities::ACTIONS,
            'tenants' => $tenants,
            'special' => TokenAbilities::SPECIAL,
        ]);
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
