<?php

declare(strict_types=1);

namespace App\Domain\Quotation\StateMachines;

use App\Domain\Quotation\Enums\QuotationStatus;
use App\Support\AbstractStateMachine;

/**
 * SRS §9 transition graph, keyed by QuotationStatus::value.
 *
 * `rejected -> superseded` isn't literally drawn in the §9 diagram (which
 * only shows `Rejected --> Sent` as a *new* quotation record being
 * created), but ReviseQuotation always marks whatever row it's revising as
 * Superseded — including a Rejected one — so the booking's quotation
 * history has exactly one non-terminal-by-supersession trail per revision
 * chain. See ReviseQuotation for where this edge is exercised.
 */
final class QuotationStateMachine extends AbstractStateMachine
{
    /**
     * @var array<string, array<int, string>>
     */
    protected array $transitions = [
        'sent' => ['accepted', 'rejected', 'superseded', 'expired'],
        'rejected' => ['superseded'],
        'accepted' => [],
        'superseded' => [],
        'expired' => [],
    ];

    public function __construct(QuotationStatus|string $initialState)
    {
        parent::__construct($initialState instanceof QuotationStatus ? $initialState->value : $initialState);
    }
}
