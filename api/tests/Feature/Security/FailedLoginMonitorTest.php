<?php

declare(strict_types=1);

use App\Domain\User\Events\RepeatedFailedLoginsDetected;
use App\Domain\User\Models\FailedLoginAttempt;
use App\Domain\User\Services\FailedLoginMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function fakeLoginRequest(): Request
{
    return Request::create('/api/v1/auth/login', 'POST', server: [
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_USER_AGENT' => 'PestTestAgent/1.0',
    ]);
}

it('records a FailedLoginAttempt row on every call', function () {
    $monitor = new FailedLoginMonitor();

    $monitor->record(fakeLoginRequest(), 'attacker@example.com');

    expect(FailedLoginAttempt::query()->where('email', 'attacker@example.com')->count())->toBe(1);
});

it('does not dispatch RepeatedFailedLoginsDetected before the threshold is crossed', function () {
    Event::fake([RepeatedFailedLoginsDetected::class]);

    $monitor = new FailedLoginMonitor();

    for ($i = 0; $i < 4; $i++) {
        $monitor->record(fakeLoginRequest(), 'attacker@example.com');
    }

    Event::assertNotDispatched(RepeatedFailedLoginsDetected::class);
});

it('dispatches RepeatedFailedLoginsDetected exactly once on the 5th attempt', function () {
    Event::fake([RepeatedFailedLoginsDetected::class]);

    $monitor = new FailedLoginMonitor();

    for ($i = 0; $i < 5; $i++) {
        $monitor->record(fakeLoginRequest(), 'attacker@example.com');
    }

    Event::assertDispatchedTimes(RepeatedFailedLoginsDetected::class, 1);
    Event::assertDispatched(function (RepeatedFailedLoginsDetected $event) {
        return $event->email === 'attacker@example.com'
            && $event->attemptCount === 5
            && $event->windowMinutes === 15;
    });
});

it('debounces between threshold multiples and refires at the next multiple', function () {
    Event::fake([RepeatedFailedLoginsDetected::class]);

    $monitor = new FailedLoginMonitor();

    // Attempts 1-9: only #5 should have fired.
    for ($i = 0; $i < 9; $i++) {
        $monitor->record(fakeLoginRequest(), 'attacker@example.com');
    }
    Event::assertDispatchedTimes(RepeatedFailedLoginsDetected::class, 1);

    // Attempt #10 crosses the next multiple of the threshold (5) and refires.
    $monitor->record(fakeLoginRequest(), 'attacker@example.com');
    Event::assertDispatchedTimes(RepeatedFailedLoginsDetected::class, 2);
});

it('scopes the count to attempts within the configured window', function () {
    Event::fake([RepeatedFailedLoginsDetected::class]);

    // 4 stale attempts outside the 15-minute window...
    FailedLoginAttempt::factory()->count(4)->create([
        'email' => 'attacker@example.com',
        'created_at' => now()->subMinutes(20),
    ]);

    $monitor = new FailedLoginMonitor();

    // ...plus 1 fresh one should NOT cross the threshold of 5.
    $monitor->record(fakeLoginRequest(), 'attacker@example.com');

    Event::assertNotDispatched(RepeatedFailedLoginsDetected::class);
});

it('keeps separate counters per email', function () {
    Event::fake([RepeatedFailedLoginsDetected::class]);

    $monitor = new FailedLoginMonitor();

    for ($i = 0; $i < 4; $i++) {
        $monitor->record(fakeLoginRequest(), 'a@example.com');
    }

    for ($i = 0; $i < 4; $i++) {
        $monitor->record(fakeLoginRequest(), 'b@example.com');
    }

    Event::assertNotDispatched(RepeatedFailedLoginsDetected::class);
});
