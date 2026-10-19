<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Livewire\Concerns\EditsTokenPermissions;
use App\Models\OAuthGrant;
use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Services\OAuth\TokenIssuer;
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

    /** OAuth connection being edited (permissions only). */
    public ?int $editingGrantId = null;

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

    public function openEditGrant(int $id): void
    {
        $this->authorizeTokens();
        $grant = $this->findGrant($id);

        $this->resetForm();
        $this->editingGrantId = $grant->id;
        $this->loadPermissions($grant->abilities, $grant->tenant_ids);
        $this->showForm = true;
    }

    public function disconnect(int $id, TokenIssuer $issuer): void
    {
        $this->authorizeTokens();
        $issuer->revoke($this->findGrant($id));
        $this->dispatch('toast', type: 'success', message: __('settings.toast_app_disconnected'));
    }

    public function save(TokenIssuer $issuer): void
    {
        $this->authorizeTokens();

        if ($this->editingGrantId !== null) {
            $this->validate($this->permissionRules(TokenAbilities::all()), $this->permissionMessages());
            [$abilities, $tenantIds] = $this->chosenPermissions();
            $issuer->updatePermissions($this->findGrant($this->editingGrantId), $abilities, $tenantIds);

            $this->dispatch('toast', type: 'success', message: __('settings.toast_app_updated'));
            $this->showForm = false;
            $this->resetForm();

            return;
        }

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
        // OAuth access tokens are short-lived and listed by connection instead.
        $tokens = auth()->user()->tokens()->whereNull('oauth_grant_id')->orderByDesc('id')->get();
        $grants = OAuthGrant::query()->active()->where('user_id', auth()->id())
            ->with('client')->withMax('tokens', 'last_used_at')->orderByDesc('id')->get();

        return view('livewire.settings.api-tokens', [
            'tokens' => $tokens,
            'summaries' => $tokens->mapWithKeys(fn (PersonalAccessToken $t): array => [$t->id => [
                'abilities' => $this->abilitiesLabel((array) $t->abilities),
                'badges' => $this->specialBadges((array) $t->abilities),
                'tenants' => $this->tenantsLabel($t->tenant_ids, $tenantNames),
            ]]),
            'grants' => $grants,
            'grantSummaries' => $grants->mapWithKeys(fn (OAuthGrant $g): array => [$g->id => [
                'abilities' => $this->abilitiesLabel($g->abilities),
                'tenants' => $this->tenantsLabel($g->tenant_ids, $tenantNames),
                'host' => parse_url((string) ($g->client?->redirect_uris[0] ?? ''), PHP_URL_HOST),
                'last_used' => $g->getAttribute('tokens_max_last_used_at') !== null
                    ? Carbon::parse($g->getAttribute('tokens_max_last_used_at'))->diffForHumans()
                    : '—',
            ]]),
            'resources' => TokenAbilities::RESOURCES,
            'actions' => TokenAbilities::ACTIONS,
            'tenants' => $tenants,
            // An AI connection always has mcp:access and never tokens:manage.
            'special' => $this->editingGrantId === null ? TokenAbilities::SPECIAL : [],
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

    private function findGrant(int $id): OAuthGrant
    {
        $grant = OAuthGrant::query()->active()->whereKey($id)->where('user_id', auth()->id())->first();
        abort_unless($grant instanceof OAuthGrant, 404);

        return $grant;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'editingGrantId', 'name', 'abilities', 'tenantScope', 'tenantIds', 'expiresAt']);
        $this->resetValidation();
    }
}
