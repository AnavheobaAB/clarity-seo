<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\Location\LocationController;
use App\Http\Controllers\Api\V1\Tenant\InvitationController;
use App\Http\Controllers\Api\V1\Tenant\MemberController;
use App\Http\Controllers\Api\V1\Tenant\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Auth routes (guest)
    Route::prefix('auth')->group(function () {
        Route::post('/register', RegisterController::class)->name('api.auth.register');
        Route::post('/login', LoginController::class)->name('api.auth.login');

        Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');
    });

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        // Tenants
        Route::apiResource('tenants', TenantController::class);
        Route::post('/tenants/{tenant}/switch', [TenantController::class, 'switch'])->name('tenants.switch');

        // Tenant Members
        Route::get('/tenants/{tenant}/members', [MemberController::class, 'index'])->name('tenants.members.index');
        Route::patch('/tenants/{tenant}/members/{member}', [MemberController::class, 'update'])->name('tenants.members.update');
        Route::delete('/tenants/{tenant}/members/{member}', [MemberController::class, 'destroy'])->name('tenants.members.destroy');
        Route::post('/tenants/{tenant}/leave', [MemberController::class, 'leave'])->name('tenants.leave');

        // Tenant Invitations
        Route::post('/tenants/{tenant}/invitations', [InvitationController::class, 'store'])->name('tenants.invitations.store');
        Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

        // Locations
        Route::post('/tenants/{tenant}/locations/bulk', [LocationController::class, 'bulkImport'])->name('tenants.locations.bulk-import');
        Route::delete('/tenants/{tenant}/locations/bulk', [LocationController::class, 'bulkDelete'])->name('tenants.locations.bulk-delete');
        Route::get('/tenants/{tenant}/locations', [LocationController::class, 'index'])->name('tenants.locations.index');
        Route::post('/tenants/{tenant}/locations', [LocationController::class, 'store'])->name('tenants.locations.store');
        Route::get('/tenants/{tenant}/locations/{location}', [LocationController::class, 'show'])->name('tenants.locations.show');
        Route::put('/tenants/{tenant}/locations/{location}', [LocationController::class, 'update'])->name('tenants.locations.update');
        Route::delete('/tenants/{tenant}/locations/{location}', [LocationController::class, 'destroy'])->name('tenants.locations.destroy');
    });
});
