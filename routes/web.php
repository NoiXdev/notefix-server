<?php

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\WorkspaceSharingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');

// Login, logout, registration and two-factor are provided by Laravel Fortify.

Route::view('/docs/api', 'docs.api')->middleware('auth')->name('docs.api');
Route::view('/security', 'security')->middleware('auth')->name('security');

// W+ self-service sharing (owner-managed members + single-use invite links).
Route::middleware('auth')->group(function () {
    Route::get('/workspaces', [WorkspaceSharingController::class, 'index'])->name('workspaces.index');
    Route::get('/workspaces/{workspace}/share', [WorkspaceSharingController::class, 'share'])->name('workspaces.share');
    Route::post('/workspaces/{workspace}/invitations', [WorkspaceSharingController::class, 'storeInvitation'])->name('workspaces.invitations.store');
    Route::delete('/workspaces/{workspace}/invitations/{invitation}', [WorkspaceSharingController::class, 'destroyInvitation'])->name('workspaces.invitations.destroy');
    Route::patch('/workspaces/{workspace}/members/{member}', [WorkspaceSharingController::class, 'updateMember'])->name('workspaces.members.update');
    Route::delete('/workspaces/{workspace}/members/{member}', [WorkspaceSharingController::class, 'destroyMember'])->name('workspaces.members.destroy');

    Route::get('/invite/{invitation:token}', [InvitationController::class, 'show'])->name('invite.show');
    Route::post('/invite/{invitation:token}/accept', [InvitationController::class, 'accept'])->name('invite.accept');
});
