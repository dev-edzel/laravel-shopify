<?php

use App\Http\Controllers\InstallationController;
use Illuminate\Support\Facades\Route;

// /shopify/auth
Route::prefix('shopify')->group(function (){
    Route::get('/', function () {
        dd('Hello');
    });
    Route::get('auth', [InstallationController::class, 'startInstallation']);
    Route::get('auth/redirect', [InstallationController::class, 'handleRedirect'])->name('app_install_redirect');
    Route::get('auth/complete', [InstallationController::class, 'completeInstallation'])->name('app_install_complete');
});