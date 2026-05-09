<?php

declare(strict_types=1);

use App\Http\Controllers\Tenancy\SwitchTenantController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::post('tenant/switch/{tenant}', SwitchTenantController::class)
    ->middleware(['auth'])
    ->name('tenant.switch');

require __DIR__.'/auth.php';
