<?php

declare(strict_types=1);

use App\Domain\Quotation\StateMachines\QuotationStateMachine;
use App\Support\IllegalStateTransitionException;

it('walks send -> accept', function () {
    $machine = new QuotationStateMachine('sent');

    expect($machine->transition('accepted'))->toBe('accepted');
});

it('walks send -> reject -> supersede (revision)', function () {
    $machine = new QuotationStateMachine('sent');
    expect($machine->transition('rejected'))->toBe('rejected');
    expect($machine->transition('superseded'))->toBe('superseded');
});

it('lets a still-sent quotation be directly superseded by a revision', function () {
    $machine = new QuotationStateMachine('sent');

    expect($machine->transition('superseded'))->toBe('superseded');
});

it('expires a sent quotation via the sweep', function () {
    $machine = new QuotationStateMachine('sent');

    expect($machine->transition('expired'))->toBe('expired');
});

it('rejects every illegal transition', function (string $from, string $to) {
    $machine = new QuotationStateMachine($from);

    expect(fn () => $machine->transition($to))->toThrow(IllegalStateTransitionException::class);
})->with([
    'accepted -> rejected' => ['accepted', 'rejected'],
    'accepted -> superseded' => ['accepted', 'superseded'],
    'rejected -> accepted' => ['rejected', 'accepted'],
    'rejected -> expired' => ['rejected', 'expired'],
    'superseded -> sent' => ['superseded', 'sent'],
    'expired -> sent' => ['expired', 'sent'],
]);
