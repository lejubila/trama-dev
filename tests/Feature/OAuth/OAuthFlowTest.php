<?php

declare(strict_types=1);

use App\Actions\OAuth\ExchangeAuthorizationCode;
use App\Enums\UserRole;
use App\Livewire\OAuth\Authorize;
use App\Livewire\Settings\ApiTokens;
use App\Models\OAuthClient;
use App\Models\OAuthGrant;
use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Api\TokenAbilities;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

const CLAUDE_CALLBACK = 'https://claude.ai/api/mcp/auth_callback';
const PKCE_VERIFIER = 'a-very-long-pkce-code-verifier-of-at-least-43-characters-0123456789';

afterEach(function (): void {
    TenantContext::clear();
    Carbon::setTestNow();
});

/**
 * The running test case, with the auth guards reset between requests.
 */
function oauthTest(): mixed
{
    /** @var TestCase $test @phpstan-ignore varTag.nativeType */
    $test = test();
    app('auth')->forgetGuards();

    return $test;
}

function registerClient(string $redirect = CLAUDE_CALLBACK): string
{
    return (string) oauthTest()->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [$redirect]])
        ->assertCreated()->json('client_id');
}

/**
 * @return array<string, string>
 */
function authorizeQuery(string $clientId, array $overrides = []): array
{
    return [
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => CLAUDE_CALLBACK,
        'code_challenge' => ExchangeAuthorizationCode::challenge(PKCE_VERIFIER),
        'code_challenge_method' => 'S256',
        'state' => 'xyz',
        'scope' => 'mcp',
        'resource' => url('/mcp'),
        ...$overrides,
    ];
}

/**
 * Consent as $admin with the given abilities; returns the query of the redirect back to the client.
 *
 * @param  list<string>  $abilities
 * @param  list<int>|null  $tenantIds
 * @return array<string, string>
 */
function consent(User $admin, string $clientId, array $abilities, ?array $tenantIds = null): array
{
    oauthTest()->actingAs($admin);
    $component = Livewire::withQueryParams(authorizeQuery($clientId))->test(Authorize::class)
        ->set('abilities', $abilities);
    if ($tenantIds !== null) {
        $component->set('tenantScope', 'selected')->set('tenantIds', $tenantIds);
    }
    $redirect = (string) $component->call('approve')->effects['redirect'];
    expect($redirect)->toStartWith(CLAUDE_CALLBACK.'?');
    parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);

    /** @var array<string, string> $query */
    return $query;
}

/**
 * @return TestResponse<JsonResponse>
 */
function exchange(string $clientId, string $code, array $overrides = []): TestResponse
{
    return oauthTest()->post('/oauth/token', [
        'grant_type' => 'authorization_code', 'client_id' => $clientId, 'code' => $code,
        'redirect_uri' => CLAUDE_CALLBACK, 'code_verifier' => PKCE_VERIFIER, 'resource' => url('/mcp'),
        ...$overrides,
    ], ['Accept' => 'application/json']);
}

/**
 * @return TestResponse<JsonResponse>
 */
function mcpWith(string $token, string $method): TestResponse
{
    return oauthTest()->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json, text/event-stream',
        'MCP-Protocol-Version' => '2025-06-18',
    ])->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method]);
}

function admin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

it('advertises OAuth discovery on a 401 from /mcp', function (): void {
    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'])
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp').'"');

    $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk()
        ->assertJson(['resource' => url('/mcp'), 'authorization_servers' => [url('/')]]);

    $this->getJson('/.well-known/oauth-authorization-server')->assertOk()->assertJson([
        'issuer' => url('/'),
        'authorization_endpoint' => url('/oauth/authorize'),
        'token_endpoint' => url('/oauth/token'),
        'registration_endpoint' => url('/oauth/register'),
        'code_challenge_methods_supported' => ['S256'],
        'authorization_response_iss_parameter_supported' => true,
    ]);
});

it('registers clients only for allowed redirect URIs', function (string $uri, bool $ok): void {
    $response = $this->postJson('/oauth/register', ['client_name' => 'X', 'redirect_uris' => [$uri]]);

    $ok ? $response->assertCreated()->assertJson(['token_endpoint_auth_method' => 'none'])
        : $response->assertStatus(400)->assertJson(['error' => 'invalid_redirect_uri']);
})->with([
    'claude.ai' => [CLAUDE_CALLBACK, true],
    'chatgpt' => ['https://chatgpt.com/connector_platform_oauth_redirect', true],
    'chatgpt per-app' => ['https://chatgpt.com/connector/oauth/abc123', true],
    'claude code' => ['http://localhost:54321/callback', true],
    'other domain' => ['https://evil.example/callback', false],
    'userinfo trick' => ['http://localhost:80@evil.example/callback', false],
    'claude.ai other path' => ['https://claude.ai/somewhere-else', false],
    'https localhost' => ['https://localhost:3000/callback', false],
]);

it('sends guests to the login page and refuses non-admins on the consent page', function (): void {
    $clientId = registerClient();

    $this->get('/oauth/authorize?'.http_build_query(authorizeQuery($clientId)))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create(['role' => UserRole::Tecnico]));
    $redirect = Livewire::withQueryParams(authorizeQuery($clientId))->test(Authorize::class)
        ->assertSet('denied', true)
        ->assertSee(__('oauth.denied'))
        ->call('deny')->effects['redirect'];
    expect($redirect)->toContain('error=access_denied')->toContain('state=xyz');

    // approve() is guarded too, not only the view.
    Livewire::withQueryParams(authorizeQuery($clientId))->test(Authorize::class)->call('approve')->assertForbidden();
});

it('validates the authorization request', function (): void {
    $clientId = registerClient();
    $this->actingAs(admin());

    Livewire::withQueryParams(authorizeQuery($clientId, ['redirect_uri' => 'https://evil.example/cb']))->test(Authorize::class)
        ->assertSet('fatal', __('oauth.err_unknown_client'))->assertNoRedirect();
    Livewire::withQueryParams(authorizeQuery('not-a-uuid'))->test(Authorize::class)
        ->assertSet('fatal', __('oauth.err_unknown_client'));

    foreach ([
        ['response_type' => 'token'],
        ['code_challenge' => ''],
        ['code_challenge_method' => 'plain'],
        ['resource' => 'https://elsewhere.example/mcp'],
    ] as $bad) {
        $redirect = (string) $this->get('/oauth/authorize?'.http_build_query(authorizeQuery($clientId, $bad)))
            ->assertRedirect()->headers->get('Location');
        expect($redirect)->toStartWith(CLAUDE_CALLBACK.'?error=')->toContain('iss=');
    }
});

it('runs the whole flow: consent, code exchange, MCP with the chosen permissions', function (): void {
    $admin = admin();
    $tenant = Tenant::factory()->create();
    $clientId = registerClient();

    $this->actingAs($admin)->get('/oauth/authorize?'.http_build_query(authorizeQuery($clientId)))
        ->assertOk()->assertSee('Claude')->assertSee('data-testid="ability-matrix"', false);

    $query = consent($admin, $clientId, ['equipment:read'], [$tenant->getKey()]);
    expect($query)->toHaveKeys(['code', 'state', 'iss'])->and($query['state'])->toBe('xyz')->and($query['iss'])->toBe(url('/'));

    $tokens = exchange($clientId, $query['code'])->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJson(['token_type' => 'Bearer', 'expires_in' => 3600, 'scope' => 'mcp'])
        ->json();

    $pat = PersonalAccessToken::findToken($tokens['access_token']);
    expect($pat?->abilities)->toEqualCanonicalizing(['equipment:read', TokenAbilities::MCP])
        ->and($pat?->tenant_ids)->toBe([$tenant->getKey()])
        ->and($pat?->oauth_grant_id)->not->toBeNull()
        ->and($pat?->expires_at?->diffInMinutes(now(), true))->toBeGreaterThan(59);

    $tools = array_column(mcpWith($tokens['access_token'], 'tools/list')->assertOk()->json('result.tools'), 'name');
    expect($tools)->toContain('list_equipment', 'get_equipment')->not->toContain('create_equipment', 'list_wifi_networks');

    // The code is single use, and replaying it kills what it produced.
    exchange($clientId, $query['code'])->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
    expect(PersonalAccessToken::findToken($tokens['access_token']))->toBeNull();
});

it('rejects a wrong verifier, redirect URI, client or an expired code', function (): void {
    $admin = admin();
    $clientId = registerClient();
    $other = registerClient();

    foreach ([
        ['code_verifier' => str_repeat('b', 64)],
        ['redirect_uri' => 'https://claude.com/api/mcp/auth_callback'],
        ['client_id' => $other],
    ] as $bad) {
        $code = consent($admin, $clientId, TokenAbilities::readOnly())['code'];
        exchange($clientId, $code, $bad)->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
    }

    $code = consent($admin, $clientId, TokenAbilities::readOnly())['code'];
    Carbon::setTestNow(now()->addMinutes(11));
    exchange($clientId, $code)->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
});

it('rotates refresh tokens and revokes the connection when an old one is replayed', function (): void {
    $admin = admin();
    $clientId = registerClient();
    $first = exchange($clientId, consent($admin, $clientId, TokenAbilities::readOnly())['code'])->json();

    $refresh = fn (string $token) => oauthTest()->post('/oauth/token', [
        'grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $token,
    ], ['Accept' => 'application/json']);

    $second = $refresh($first['refresh_token'])->assertOk()->json();
    expect($second['refresh_token'])->not->toBe($first['refresh_token'])
        ->and(PersonalAccessToken::findToken($first['access_token']))->toBeNull();
    mcpWith($second['access_token'], 'tools/list')->assertOk();

    // The old refresh token leaked: the whole connection is cut.
    $refresh($first['refresh_token'])->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
    expect(PersonalAccessToken::findToken($second['access_token']))->toBeNull()
        ->and(OAuthGrant::query()->sole()->revoked_at)->not->toBeNull();
    $refresh($second['refresh_token'])->assertStatus(400);
});

it('stops refreshing once the user is no longer an admin', function (): void {
    $admin = admin();
    $clientId = registerClient();
    $tokens = exchange($clientId, consent($admin, $clientId, TokenAbilities::readOnly())['code'])->json();
    $admin->update(['role' => UserRole::Tecnico]);

    $this->post('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $tokens['refresh_token']])
        ->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
});

it('expires access tokens after an hour', function (): void {
    $admin = admin();
    $clientId = registerClient();
    $tokens = exchange($clientId, consent($admin, $clientId, TokenAbilities::readOnly())['code'])->json();

    Carbon::setTestNow(now()->addMinutes(61));
    mcpWith($tokens['access_token'], 'tools/list')->assertUnauthorized()->assertHeader('WWW-Authenticate');
});

it('revokes a connection from the endpoint or the token page, and edits its permissions', function (): void {
    $admin = admin();
    $clientId = registerClient();
    $a = exchange($clientId, consent($admin, $clientId, TokenAbilities::readOnly())['code'])->json();

    $this->post('/oauth/revoke', ['token' => $a['refresh_token'], 'client_id' => $clientId])->assertOk();
    expect(PersonalAccessToken::findToken($a['access_token']))->toBeNull();

    $b = exchange($clientId, consent($admin, $clientId, TokenAbilities::readOnly())['code'])->json();
    $grant = OAuthGrant::query()->active()->sole();
    $this->actingAs($admin);

    Livewire::test(ApiTokens::class)
        ->assertSee('Claude')
        ->assertDontSee($grant->tokens()->sole()->name.'</td>', false)
        ->call('openEditGrant', $grant->id)
        ->set('abilities', ['equipment:read', 'equipment:create'])
        ->call('save')
        ->assertHasNoErrors();
    expect(PersonalAccessToken::findToken($b['access_token'])?->abilities)
        ->toEqualCanonicalizing(['equipment:read', 'equipment:create', TokenAbilities::MCP]);

    Livewire::test(ApiTokens::class)->call('disconnect', $grant->id);
    expect(PersonalAccessToken::findToken($b['access_token']))->toBeNull()
        ->and($grant->fresh()?->revoked_at)->not->toBeNull();
});

it('never grants tokens:manage through consent', function (): void {
    $clientId = registerClient();
    $this->actingAs(admin());

    Livewire::withQueryParams(authorizeQuery($clientId))->test(Authorize::class)
        ->set('abilities', [TokenAbilities::MANAGE_TOKENS])
        ->call('approve')
        ->assertHasErrors('abilities.0');
});

it('replaces the previous connection when the same app reconnects', function (): void {
    $admin = admin();
    $clientId = registerClient();
    $first = exchange($clientId, consent($admin, $clientId, TokenAbilities::readOnly())['code'])->json();
    exchange($clientId, consent($admin, $clientId, TokenAbilities::readOnly())['code'])->assertOk();

    expect(OAuthGrant::query()->active()->count())->toBe(1)
        ->and(PersonalAccessToken::findToken($first['access_token']))->toBeNull();
});

it('is entirely disabled in demo mode', function (): void {
    $clientId = registerClient();
    config(['demo.enabled' => true]);

    $this->getJson('/.well-known/oauth-protected-resource')->assertNotFound();
    $this->getJson('/.well-known/oauth-authorization-server')->assertNotFound();
    $this->postJson('/oauth/register', ['redirect_uris' => [CLAUDE_CALLBACK]])->assertNotFound();
    $this->post('/oauth/token', ['grant_type' => 'refresh_token'])->assertNotFound();
    $this->actingAs(admin())->get('/oauth/authorize?'.http_build_query(authorizeQuery($clientId)))->assertNotFound();
});

it('prunes clients that were never authorized', function (): void {
    $stale = OAuthClient::factory()->create(['created_at' => now()->subDays(8)]);
    $fresh = OAuthClient::factory()->create();

    expect(Artisan::call('oauth:prune'))->toBe(0);

    expect(OAuthClient::query()->find($stale->id))->toBeNull()->and(OAuthClient::query()->find($fresh->id))->not->toBeNull();
});
