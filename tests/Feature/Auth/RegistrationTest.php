<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Route;

test('public registration is disabled', function () {
    $this->get('/register')->assertNotFound();
});

test('the register route is not registered', function () {
    expect(Route::has('register'))->toBeFalse();
});
