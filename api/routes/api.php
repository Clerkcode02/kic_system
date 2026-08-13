<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\Catalog\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Provider\Business\AvailabilityController as ProviderAvailabilityManagementController;
use App\Http\Controllers\Api\V1\Provider\Business\BusinessDocumentController;
use App\Http\Controllers\Api\V1\Provider\Business\BusinessProfileController;
use App\Http\Controllers\Api\V1\Provider\Business\SubmitForVerificationController;
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
use App\Http\Controllers\Api\V1\Shared\Business\ProviderAvailabilityController;
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

    Route::get('providers/{business}/availability', [ProviderAvailabilityController::class, 'show'])->name('providers.availability');
});

Route::prefix('v1/admin')->name('admin.')->middleware(['api.protected', 'role:admin,super_admin'])->group(function () {
    Route::post('categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::patch('categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('categories/reorder', [AdminCategoryController::class, 'reorder'])->name('categories.reorder');
});

Route::prefix('v1/provider')->name('provider.')->middleware(['api.protected', 'role:provider_owner,provider_staff'])->group(function () {
    Route::get('me', [BusinessProfileController::class, 'show'])->name('me.show');
    Route::patch('me', [BusinessProfileController::class, 'update'])->name('me.update');

    Route::post('me/documents/upload-url', [BusinessDocumentController::class, 'uploadUrl'])->name('me.documents.upload-url');
    Route::post('me/documents', [BusinessDocumentController::class, 'store'])->name('me.documents.store');

    Route::post('me/submit-for-verification', SubmitForVerificationController::class)->name('me.submit-for-verification');

    Route::get('me/services', [ProviderServiceController::class, 'index'])->name('services.index');
    Route::post('services', [ProviderServiceController::class, 'store'])->name('services.store');
    Route::get('services/{service}', [ProviderServiceController::class, 'show'])->name('services.show');
    Route::patch('services/{service}', [ProviderServiceController::class, 'update'])->name('services.update');
    Route::delete('services/{service}', [ProviderServiceController::class, 'destroy'])->name('services.destroy');

    Route::get('me/availability', [ProviderAvailabilityManagementController::class, 'show'])->name('me.availability.show');
    Route::put('me/availability', [ProviderAvailabilityManagementController::class, 'update'])->name('me.availability.update');
});
