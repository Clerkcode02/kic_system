<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\Catalog\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Provider\Catalog\ServiceController as ProviderServiceController;
use App\Http\Controllers\Api\V1\Shared\Auth\LoginController;
use App\Http\Controllers\Api\V1\Shared\Auth\LogoutAllDevicesController;
use App\Http\Controllers\Api\V1\Shared\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Shared\Auth\MeController;
use App\Http\Controllers\Api\V1\Shared\Auth\RegisterBusinessController;
use App\Http\Controllers\Api\V1\Shared\Auth\RegisterCustomerController;
use App\Http\Controllers\Api\V1\Shared\Auth\RegisterFreelancerController;
use App\Http\Controllers\Api\V1\Shared\Auth\ResendEmailVerificationController;
use App\Http\Controllers\Api\V1\Shared\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\Shared\Catalog\CategoryController;
use App\Http\Controllers\Api\V1\Shared\Catalog\ServiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->name('auth.')->middleware('throttle:auth')->group(function () {
    Route::post('register/customer', RegisterCustomerController::class)->name('register.customer');
    Route::post('register/business', RegisterBusinessController::class)->name('register.business');
    Route::post('register/freelancer', RegisterFreelancerController::class)->name('register.freelancer');

    Route::post('login', LoginController::class)->name('login');

    Route::post('email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('email/resend', ResendEmailVerificationController::class)->name('verification.resend');

    Route::middleware(['auth:sanctum', 'not-suspended'])->group(function () {
        Route::get('me', MeController::class)->name('me');
        Route::post('logout', LogoutController::class)->name('logout');
        Route::post('logout-all-devices', LogoutAllDevicesController::class)->name('logout-all-devices');
    });
});

Route::prefix('v1')->name('catalog.')->group(function () {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show');
    Route::get('services/{service}/pricing', [ServiceController::class, 'pricing'])->name('services.pricing');
});

Route::prefix('v1/admin')->name('admin.')->middleware(['api.protected', 'role:admin,super_admin'])->group(function () {
    Route::post('categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::patch('categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('categories/reorder', [AdminCategoryController::class, 'reorder'])->name('categories.reorder');
});

Route::prefix('v1/provider')->name('provider.')->middleware(['api.protected', 'role:provider_owner,provider_staff'])->group(function () {
    Route::post('services', [ProviderServiceController::class, 'store'])->name('services.store');
});
