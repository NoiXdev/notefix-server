<?php

use App\Http\Controllers\ImageController;
use App\Http\Controllers\OAuthConfigController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\VaultKeyController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/oauth/config', [OAuthConfigController::class, 'show']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::middleware('auth:api')->group(function () {
    Route::get('/workspaces', [WorkspaceController::class, 'index']);
    Route::get('/workspaces/{workspace}/changes', [SyncController::class, 'pull']);
    Route::post('/workspaces/{workspace}/changes', [SyncController::class, 'push']);
    Route::get('/workspaces/{workspace}/images', [ImageController::class, 'index']);
    Route::post('/workspaces/{workspace}/images', [ImageController::class, 'store']);
    Route::get('/workspaces/{workspace}/image', [ImageController::class, 'show']);
    Route::post('/workspaces/{workspace}/vault', [VaultKeyController::class, 'create']);
    Route::put('/workspaces/{workspace}/vault/keys/me', [VaultKeyController::class, 'putMine']);
    Route::post('/workspaces/{workspace}/vault/invites/{invitation}', [VaultKeyController::class, 'attachInvite']);
    Route::get('/workspaces/{workspace}/vault/invites/by-token/{token}', [VaultKeyController::class, 'resolveInvite']);
    Route::get('/workspaces/{workspace}/vault/invites/{invitation}', [VaultKeyController::class, 'showInvite']);
    Route::post('/workspaces/{workspace}/vault/invites/{invitation}/accept', [VaultKeyController::class, 'acceptInvite']);
    Route::post('/workspaces/{workspace}/vault/rotate', [VaultKeyController::class, 'rotate']);
    Route::post('/workspaces/{workspace}/vault/recovery', [VaultKeyController::class, 'putRecovery']);
    Route::get('/workspaces/{workspace}/members', [VaultKeyController::class, 'members']);
});
