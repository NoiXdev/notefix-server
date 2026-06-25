<?php

use App\Http\Controllers\OAuthConfigController;
use App\Http\Controllers\SyncController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/oauth/config', [OAuthConfigController::class, 'show']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::middleware('auth:api')->group(function () {
    Route::get('/workspaces/{workspace}/changes', [SyncController::class, 'pull']);
    Route::post('/workspaces/{workspace}/changes', [SyncController::class, 'push']);
});
