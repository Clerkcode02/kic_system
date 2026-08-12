<?php

declare(strict_types=1);

use App\Support\IllegalStateTransitionException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

test('ValidationException renders as 422 with field-level errors', function () {
    Route::get('/__test/validation', function () {
        throw ValidationException::withMessages(['email' => ['The email field is required.']]);
    });

    $this->getJson('/__test/validation')
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors' => ['email']]);
});

test('AuthorizationException renders as 403', function () {
    Route::get('/__test/authorization', function () {
        throw new AuthorizationException('This action is unauthorized.');
    });

    $this->getJson('/__test/authorization')
        ->assertStatus(403)
        ->assertJsonStructure(['message']);
});

test('IllegalStateTransitionException renders as 409 with a clear JSON body', function () {
    Route::get('/__test/illegal-transition', function () {
        throw new IllegalStateTransitionException('pending', 'completed', 'Booking');
    });

    $this->getJson('/__test/illegal-transition')
        ->assertStatus(409)
        ->assertJson([
            'error' => 'illegal_state_transition',
            'from' => 'pending',
            'to' => 'completed',
        ])
        ->assertJsonStructure(['message', 'error', 'from', 'to']);
});

test('ModelNotFoundException renders as 404', function () {
    Route::get('/__test/not-found', function () {
        throw new ModelNotFoundException('No query results for model.');
    });

    $this->getJson('/__test/not-found')
        ->assertStatus(404)
        ->assertJsonStructure(['message']);
});
