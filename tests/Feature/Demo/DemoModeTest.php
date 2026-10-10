<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\Settings\ApiTokens;
use App\Livewire\Sites\Index as SitesIndex;
use App\Livewire\Tenants\Manage as TenantsManage;
use App\Livewire\Users\Index as UsersIndex;
use App\Models\DeviceIcon;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Icons\DefaultIcons;
use App\Services\TenantTransfer\TenantArchiveExporter;
use App\Support\Demo;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(function (): void {
    config(['demo.enabled' => true, 'demo.user.email' => 'demo@trama.test', 'demo.user.password' => 'demo']);
});

afterEach(function (): void {
    TenantContext::clear();
});

function demoUser(?Tenant $tenant = null): User
{
    $tenant ??= Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create(['email' => 'demo@trama.test', 'role' => UserRole::Admin]);
    actingAsInTenant($user, $tenant);

    return $user;
}

it('forbids user management even to the admin demo account', function (): void {
    $user = demoUser();
    $other = User::factory()->create();

    expect($user->can('viewAny', User::class))->toBeFalse()
        ->and($user->can('create', User::class))->toBeFalse()
        ->and($user->can('update', $other))->toBeFalse()
        ->and($user->can('delete', $other))->toBeFalse();

    Livewire::test(UsersIndex::class)->assertForbidden();
});

it('keeps user management for admins when demo mode is off', function (): void {
    config(['demo.enabled' => false]);
    demoUser();

    Livewire::test(UsersIndex::class)->assertOk();
});

it('lets the demo account change regular data', function (): void {
    demoUser();

    Livewire::test(SitesIndex::class)
        ->call('openCreate')
        ->set('name', 'Demo site')
        ->call('save')
        ->assertHasNoErrors();

    expect(Site::query()->where('name', 'Demo site')->exists())->toBeTrue();
});

it('blocks API tokens, member assignment and account changes', function (): void {
    $tenant = Tenant::factory()->create();
    demoUser($tenant);
    $cliente = User::factory()->create(['role' => UserRole::Cliente]);

    Livewire::test(ApiTokens::class)->assertForbidden();

    Livewire::test(TenantsManage::class, ['tenant' => $tenant])
        ->set('activeTab', 'members')
        ->set('newUserId', $cliente->getKey())
        ->call('addMember')
        ->assertForbidden();

    Volt::test('profile.update-password-form')->call('updatePassword')->assertForbidden();
    Volt::test('profile.delete-user-form')->call('deleteUser')->assertForbidden();
    Volt::test('profile.update-profile-information-form')->call('updateProfileInformation')->assertForbidden();
});

it('stores the language choice in the session, not on the shared account', function (): void {
    $user = demoUser();

    Volt::test('profile.update-language-form')->set('locale', 'en')->call('updateLanguage');

    expect(session('locale'))->toBe('en')
        ->and($user->fresh()->preferences['locale'] ?? null)->toBeNull();
});

it('keeps the active tenant per session instead of on the shared account', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $user = demoUser($a);
    $user->forceFill(['current_tenant_id' => $a->getKey()])->save();

    $this->post(route('tenant.switch', $b))->assertRedirect();

    expect(session(CurrentTenant::SESSION_KEY))->toBe($b->getKey())
        ->and($user->fresh()->current_tenant_id)->toBe($a->getKey());
});

it('offers one-click login on the login page', function (): void {
    User::factory()->create(['email' => 'demo@trama.test', 'role' => UserRole::Admin]);

    $this->get('/login')->assertOk()->assertSee(__('demo.login_button'))->assertSee('demo@trama.test');

    Volt::test('pages.auth.login')->call('loginAsDemo')->assertRedirect(route('dashboard', absolute: false));

    expect(auth()->user()?->email)->toBe('demo@trama.test');
});

it('hides the demo login and password reset outside / inside demo mode', function (): void {
    $this->get('/forgot-password')->assertNotFound();

    config(['demo.enabled' => false]);
    $this->get('/forgot-password')->assertOk();
    $this->get('/login')->assertDontSee(__('demo.login_button'));
    Volt::test('pages.auth.login')->call('loginAsDemo')->assertNotFound();
});

it('shows the demo banner only in demo mode', function (): void {
    demoUser();

    $this->get(route('sites.index'))->assertOk()->assertSee(__('demo.next_reset'))->assertSee('[DEMO]', false);

    config(['demo.enabled' => false]);
    $this->get(route('sites.index'))->assertOk()->assertDontSee(__('demo.next_reset'));
});

it('computes the next reset in the configured timezone', function (): void {
    config(['demo.reset_time' => '03:00', 'demo.timezone' => 'Europe/Rome']);
    $this->travelTo(CarbonImmutable::parse('2026-10-10 02:30', 'Europe/Rome'));
    expect(Demo::nextResetAt()->format('Y-m-d H:i'))->toBe('2026-10-10 03:00');

    $this->travelTo(CarbonImmutable::parse('2026-10-10 03:30', 'Europe/Rome'));
    expect(Demo::nextResetAt()->format('Y-m-d H:i'))->toBe('2026-10-11 03:00');
});

it('refuses to reset when demo mode is off', function (): void {
    config(['demo.enabled' => false]);
    Site::factory()->for(Tenant::factory())->create();

    $this->artisan('demo:reset', ['--no-maintenance' => true])->assertFailed();

    expect(Site::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('wipes everything and restores the demo archives', function (): void {
    Storage::fake('public');
    $dir = sys_get_temp_dir().'/trama-demo-'.uniqid();
    mkdir($dir);

    // Build the demo archive from a curated tenant…
    $curated = Tenant::factory()->create(['name' => 'Demo Corp', 'slug' => 'demo-corp']);
    TenantContext::setId($curated->getKey());
    Site::factory()->count(2)->create();
    copy(app(TenantArchiveExporter::class)->export($curated), $dir.'/demo.zip');

    // …then pollute the installation the way visitors would.
    $junk = Tenant::factory()->create(['name' => 'Visitor junk']);
    TenantContext::setId($junk->getKey());
    Site::factory()->create(['name' => 'Junk site']);
    User::factory()->create(['email' => 'intruder@example.com']);
    Storage::disk('public')->put("rack-photos/{$junk->getKey()}/1/x.jpg", 'X');
    TenantContext::clear();
    config(['demo.archive' => $dir]);

    $this->artisan('demo:reset', ['--no-maintenance' => true])->assertSuccessful();

    expect(Tenant::query()->pluck('name')->all())->toBe(['Demo Corp'])
        ->and(Site::query()->withoutGlobalScopes()->count())->toBe(2)
        ->and(User::query()->pluck('email')->all())->toBe(['demo@trama.test'])
        ->and(User::query()->first()->role)->toBe(UserRole::Admin)
        ->and(Storage::disk('public')->exists("rack-photos/{$junk->getKey()}/1/x.jpg"))->toBeFalse()
        ->and(DeviceIcon::query()->whereNull('tenant_id')->count())->toBe(count((new DefaultIcons)->bundled()))
        ->and(Cache::get(Demo::LAST_RESET_KEY))->toBeInt();

    array_map('unlink', glob($dir.'/*'));
    rmdir($dir);
});
