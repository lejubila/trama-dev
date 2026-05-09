<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ConnectionController;
use App\Http\Controllers\Api\V1\EquipmentController;
use App\Http\Controllers\Api\V1\NetworkInterfaceController;
use App\Http\Controllers\Api\V1\RackController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\SiteController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\TopologyController;
use Illuminate\Support\Facades\Route;

/*
 * Token endpoints — auth via Sanctum but NO tenant header (the user might
 * want to mint a token before knowing which tenant to scope it to).
 */
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::get('tokens', [TokenController::class, 'index'])->name('api.v1.tokens.index');
    Route::post('tokens', [TokenController::class, 'store'])->name('api.v1.tokens.store');
    Route::delete('tokens/{id}', [TokenController::class, 'destroy'])
        ->whereNumber('id')
        ->name('api.v1.tokens.destroy');
});

Route::prefix('v1')->middleware(['auth:sanctum', 'tenant.required', 'throttle:api'])->name('api.v1.')->group(function (): void {
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
});
