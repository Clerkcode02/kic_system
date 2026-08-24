<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// SRS §21 deployment prompt — readiness check for the load balancer,
// distinct from the built-in `/up` liveness probe (bootstrap/app.php).
// Deliberately outside /api/v1: it's an infra endpoint, not an API
// resource, and must never require auth.
Route::get('/health', HealthController::class);
