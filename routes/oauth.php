<?php

declare(strict_types=1);

use App\Http\Controllers\OAuth\MetadataController;
use App\Http\Controllers\OAuth\RegisterController;
use App\Http\Controllers\OAuth\RevokeController;
use App\Http\Controllers\OAuth\TokenController;
use App\Livewire\OAuth\Authorize;
use Illuminate\Support\Facades\Route;

/*
 * OAuth 2.1 authorization server for the MCP endpoint (claude.ai, ChatGPT).
 * Loaded from bootstrap/app.php under the demo.disabled middleware.
 */
Route::get('.well-known/oauth-protected-resource', [MetadataController::class, 'protectedResource'])
    ->name('oauth.metadata.resource');
// RFC 9728 path-suffixed form. The name is the one laravel/mcp looks for to
// put this URL in the WWW-Authenticate header of a 401 from /mcp.
Route::get('.well-known/oauth-protected-resource/{path}', [MetadataController::class, 'protectedResource'])
    ->where('path', 'mcp')
    ->name('mcp.oauth.protected-resource.nested');
Route::get('.well-known/oauth-authorization-server', [MetadataController::class, 'authorizationServer'])
    ->name('oauth.metadata.server');

// Called server-to-server by the AI platforms: no session, no CSRF.
Route::prefix('oauth')->name('oauth.')->group(function (): void {
    Route::post('register', RegisterController::class)->middleware('throttle:oauth-register')->name('register');
    Route::post('token', TokenController::class)->middleware('throttle:oauth')->name('token');
    Route::post('revoke', RevokeController::class)->middleware('throttle:oauth')->name('revoke');
});

// The consent page, in the browser of the admin.
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('oauth/authorize', Authorize::class)->name('oauth.authorize');
});
