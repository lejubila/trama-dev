<?php

declare(strict_types=1);

namespace App\Livewire\OAuth;

use App\Actions\OAuth\IssueAuthorizationCode;
use App\Livewire\Concerns\EditsTokenPermissions;
use App\Models\OAuthClient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OAuth\OAuthServer;
use App\Support\Api\TokenAbilities;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * OAuth consent page: an admin decides what an AI client (claude.ai,
 * ChatGPT…) may do in Trama, with the same matrix used for API tokens.
 */
#[Layout('layouts.guest', ['wide' => true])]
class Authorize extends Component
{
    use EditsTokenPermissions;

    // The authorization request, fixed once validated: the browser must not
    // be able to swap the client or the redirect target.
    #[Locked]
    public ?string $clientId = null;

    #[Locked]
    public string $clientName = '';

    #[Locked]
    public string $redirectUri = '';

    #[Locked]
    public ?string $state = null;

    #[Locked]
    public string $codeChallenge = '';

    #[Locked]
    public ?string $resource = null;

    /** Set when the request cannot be answered with a redirect (unknown client / redirect URI). */
    #[Locked]
    public ?string $fatal = null;

    /** The user may not connect AI clients (not an admin). */
    #[Locked]
    public bool $denied = false;

    public function mount(): void
    {
        $query = request()->query();
        $clientId = is_string($query['client_id'] ?? null) ? $query['client_id'] : '';
        $redirectUri = is_string($query['redirect_uri'] ?? null) ? $query['redirect_uri'] : '';

        $client = Str::isUuid($clientId) ? OAuthClient::query()->find($clientId) : null;
        // RFC 6749 §4.1.2.1: never redirect to an unverified URI.
        if ($client === null || ! $client->allowsRedirect($redirectUri)) {
            $this->fatal = __('oauth.err_unknown_client');

            return;
        }

        $this->clientId = $client->id;
        $this->clientName = $client->name;
        $this->redirectUri = $redirectUri;
        $this->state = is_string($query['state'] ?? null) ? $query['state'] : null;

        $error = match (true) {
            ($query['response_type'] ?? null) !== 'code' => 'unsupported_response_type',
            ! is_string($query['code_challenge'] ?? null) || strlen($query['code_challenge']) < 43 || strlen($query['code_challenge']) > 128 => 'invalid_request',
            ($query['code_challenge_method'] ?? null) !== 'S256' => 'invalid_request',
            ! OAuthServer::isOwnResource(is_string($query['resource'] ?? null) ? $query['resource'] : null) => 'invalid_target',
            default => null,
        };
        if ($error !== null) {
            $this->redirect($this->callbackUrl(['error' => $error]));

            return;
        }

        $this->codeChallenge = $query['code_challenge'];
        $this->resource = is_string($query['resource'] ?? null) && $query['resource'] !== '' ? $query['resource'] : null;
        $this->denied = ! Gate::allows('manage-api-tokens');
        $this->abilities = TokenAbilities::readOnly();
    }

    public function approve(IssueAuthorizationCode $issue): void
    {
        abort_if($this->fatal !== null || $this->clientId === null, 400);
        Gate::authorize('manage-api-tokens');

        $this->validate($this->permissionRules(TokenAbilities::all()), $this->permissionMessages());
        [$abilities, $tenantIds] = $this->chosenPermissions();

        /** @var User $user */
        $user = auth()->user();
        $code = $issue->handle(
            $user,
            OAuthClient::query()->findOrFail($this->clientId),
            $abilities,
            $tenantIds,
            $this->redirectUri,
            $this->codeChallenge,
            $this->resource,
        );

        $this->redirect($this->callbackUrl(['code' => $code]));
    }

    public function deny(): void
    {
        abort_if($this->fatal !== null, 400);

        $this->redirect($this->callbackUrl(['error' => 'access_denied']));
    }

    public function render(): View
    {
        return view('livewire.oauth.authorize', [
            'redirectHost' => parse_url($this->redirectUri, PHP_URL_HOST),
            'resources' => TokenAbilities::RESOURCES,
            'actions' => TokenAbilities::ACTIONS,
            // mcp:access is implied; tokens:manage is never offered to an AI.
            'special' => [],
            'tenants' => $this->denied || $this->fatal !== null ? collect() : Tenant::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Back to the client, with `state` and RFC 9207 `iss` on every response.
     *
     * @param  array<string, string>  $params
     */
    private function callbackUrl(array $params): string
    {
        $params = array_filter([...$params, 'state' => $this->state, 'iss' => OAuthServer::issuer()], fn ($v) => $v !== null);

        return $this->redirectUri.(str_contains($this->redirectUri, '?') ? '&' : '?').http_build_query($params);
    }
}
