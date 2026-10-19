<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ConnectionController;
use App\Http\Controllers\Api\V1\EquipmentController;
use App\Http\Controllers\Api\V1\NetworkInterfaceController;
use App\Http\Controllers\Api\V1\RackController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\SiteController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\TopologyController;
use App\Http\Controllers\Api\V1\VpnRemoteAccessController;
use App\Http\Controllers\Api\V1\VpnRemoteClientController;
use App\Http\Controllers\Api\V1\VpnSiteToSiteController;
use App\Http\Controllers\Api\V1\WifiClientController;
use App\Http\Controllers\Api\V1\WifiNetworkController;
use Illuminate\Support\Facades\Route;

/*
 * Token endpoints — admin only, and the calling token must hold
 * tokens:manage. No tenant header (a token isn't tied to one tenant).
 */
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api', 'can:manage-api-tokens', 'abilities:tokens:manage'])->group(function (): void {
    Route::get('tokens', [TokenController::class, 'index'])->name('api.v1.tokens.index');
    Route::post('tokens', [TokenController::class, 'store'])->name('api.v1.tokens.store');
    Route::delete('tokens/{id}', [TokenController::class, 'destroy'])
        ->whereNumber('id')
        ->name('api.v1.tokens.destroy');
});

// Tenant discovery: which X-Tenant-Id values this token may use.
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api', 'token.ability'])->name('api.v1.')->group(function (): void {
    Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
});

// Every route here needs the matching "{resource}:{action}" token ability (see TokenAbilities).
Route::prefix('v1')->middleware(['auth:sanctum', 'tenant.required', 'token.ability', 'throttle:api'])->name('api.v1.')->group(function (): void {
    Route::apiResource('sites', SiteController::class)->names('sites');
    Route::apiResource('sites.rooms', RoomController::class)->names('sites.rooms');
    Route::apiResource('rooms.racks', RackController::class)->shallow()->names('racks');
    Route::apiResource('equipment', EquipmentController::class)->parameters(['equipment' => 'equipment'])->names('equipment');
    Route::apiResource('equipment.interfaces', NetworkInterfaceController::class)
        ->parameters(['equipment' => 'equipment', 'interfaces' => 'interface'])
        ->shallow()
        ->names('interfaces');
    Route::apiResource('connections', ConnectionController::class)->names('connections');
    Route::get('topology', TopologyController::class)->name('topology');

    Route::apiResource('wifi-networks', WifiNetworkController::class)->names('wifi-networks');
    Route::get('wifi-networks/{wifi_network}/clients', [WifiClientController::class, 'index'])->name('wifi-networks.clients.index');
    Route::post('wifi-networks/{wifi_network}/clients', [WifiClientController::class, 'store'])->name('wifi-networks.clients.store');
    Route::delete('wifi-networks/{wifi_network}/clients/{client}', [WifiClientController::class, 'destroy'])->name('wifi-networks.clients.destroy');

    Route::prefix('vpn')->name('vpn.')->group(function (): void {
        Route::apiResource('site-to-site', VpnSiteToSiteController::class)->parameters(['site-to-site' => 'vpn'])->names('site-to-site');
        Route::apiResource('remote-access', VpnRemoteAccessController::class)->parameters(['remote-access' => 'vpn'])->names('remote-access');
        Route::get('remote-access/{vpn}/clients', [VpnRemoteClientController::class, 'index'])->name('remote-access.clients.index');
        Route::post('remote-access/{vpn}/clients', [VpnRemoteClientController::class, 'store'])->name('remote-access.clients.store');
        Route::delete('remote-access/{vpn}/clients/{client}', [VpnRemoteClientController::class, 'destroy'])->name('remote-access.clients.destroy');
    });
});
