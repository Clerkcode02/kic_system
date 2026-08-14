<?php

declare(strict_types=1);

use App\Domain\Booking\StateMachines\BookingStateMachine;
use App\Support\IllegalStateTransitionException;

it('walks the full SRS §8 happy path from pending to completed', function () {
    $machine = new BookingStateMachine('pending');

    expect($machine->transition('waiting_for_quotation'))->toBe('waiting_for_quotation');
    expect($machine->transition('quotation_sent'))->toBe('quotation_sent');
    expect($machine->transition('waiting_for_customer'))->toBe('waiting_for_customer');
    expect($machine->transition('accepted'))->toBe('accepted');
    expect($machine->transition('scheduled'))->toBe('scheduled');
    expect($machine->transition('in_progress'))->toBe('in_progress');
    expect($machine->transition('completed'))->toBe('completed');
    expect($machine->transition('refunded'))->toBe('refunded');
});

it('lets a fixed-price booking skip quoting straight to scheduled', function () {
    $machine = new BookingStateMachine('pending');

    expect($machine->transition('scheduled'))->toBe('scheduled');
});

it('lets a customer request a quotation revision', function () {
    $machine = new BookingStateMachine('waiting_for_customer');

    expect($machine->transition('waiting_for_quotation'))->toBe('waiting_for_quotation');
});

it('rejects every illegal transition in the §8 graph with a 409-mapped exception', function (string $from, string $to) {
    $machine = new BookingStateMachine($from);

    expect(fn () => $machine->transition($to))->toThrow(IllegalStateTransitionException::class);
})->with([
    'pending -> in_progress' => ['pending', 'in_progress'],
    'pending -> completed' => ['pending', 'completed'],
    'waiting_for_quotation -> scheduled' => ['waiting_for_quotation', 'scheduled'],
    'quotation_sent -> accepted' => ['quotation_sent', 'accepted'],
    'scheduled -> completed' => ['scheduled', 'completed'],
    'in_progress -> scheduled' => ['in_progress', 'scheduled'],
    'declined -> pending' => ['declined', 'pending'],
    'completed -> in_progress' => ['completed', 'in_progress'],
    'completed -> cancelled' => ['completed', 'cancelled'],
    'cancelled -> scheduled' => ['cancelled', 'scheduled'],
    'refunded -> completed' => ['refunded', 'completed'],
    'quotation_expired -> waiting_for_quotation' => ['quotation_expired', 'waiting_for_quotation'],
]);

it('reports the offending from/to state on the exception', function () {
    $machine = new BookingStateMachine('completed');

    try {
        $machine->transition('scheduled');
        $this->fail('Expected IllegalStateTransitionException to be thrown.');
    } catch (IllegalStateTransitionException $e) {
        expect($e->from)->toBe('completed');
        expect($e->to)->toBe('scheduled');
    }
});
