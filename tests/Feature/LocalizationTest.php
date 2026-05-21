<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Volt\Volt;

it('uses Italian as fallback for guests without a matching Accept-Language', function (): void {
    $this->get('/login', ['Accept-Language' => 'fr-FR,fr;q=0.9'])->assertOk();

    expect(app()->getLocale())->toBe('it');
});

it('negotiates English from the Accept-Language header for guests', function (): void {
    $this->get('/login', ['Accept-Language' => 'en-US,en;q=0.9'])->assertOk();

    expect(app()->getLocale())->toBe('en');
});

it('honors an authenticated user locale preference over Accept-Language', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
        'preferences' => ['locale' => 'en'],
    ]);

    $this->actingAs($user)->get('/profile', ['Accept-Language' => 'it-IT'])->assertOk();

    expect(app()->getLocale())->toBe('en')
        ->and($user->preferredLocale())->toBe('en');
});

it('falls back to negotiation when the user has no stored preference', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin, 'preferences' => []]);

    $this->actingAs($user)->get('/profile', ['Accept-Language' => 'en'])->assertOk();

    expect(app()->getLocale())->toBe('en');
});

it('persists the locale preference via setLocalePreference, preserving other keys', function (): void {
    $user = User::factory()->create(['preferences' => ['theme' => 'dark']]);

    $user->setLocalePreference('en');

    expect($user->fresh()->preferences)->toBe(['theme' => 'dark', 'locale' => 'en']);
});

it('rejects an unsupported locale preference', function (): void {
    $user = User::factory()->create(['preferences' => ['locale' => 'de']]);

    expect($user->preferredLocale())->toBeNull();
});

it('saves the chosen locale through the profile language form and redirects', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin, 'preferences' => ['theme' => 'dark']]);

    Volt::actingAs($user)
        ->test('profile.update-language-form')
        ->set('locale', 'en')
        ->call('updateLanguage')
        ->assertRedirect(route('profile'));

    expect($user->fresh()->preferences)->toBe(['theme' => 'dark', 'locale' => 'en']);
});

it('rejects an unsupported locale in the profile language form', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    Volt::actingAs($user)
        ->test('profile.update-language-form')
        ->set('locale', 'de')
        ->call('updateLanguage')
        ->assertHasErrors('locale');
});

it('translates the same key differently per locale', function (): void {
    app()->setLocale('it');
    expect(__('nav.topology'))->toBe('Topologia');

    app()->setLocale('en');
    expect(__('nav.topology'))->toBe('Topology');
});
