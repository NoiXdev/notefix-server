<?php

use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');

// Login, logout, registration and two-factor are provided by Laravel Fortify.

Route::view('/docs/api', 'docs.api')->middleware('auth')->name('docs.api');
Route::view('/security', 'security')->middleware('auth')->name('security');
