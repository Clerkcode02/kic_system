<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Payment\Services\PaymentGateway;
use App\Domain\Payment\Services\StubPaymentGateway;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Every test gets the Phase 5 stub by default, so a test unrelated
        // to payments never trips a real (or even mocked) Stripe call just
        // by walking through AcceptQuotation/HireFreelancer. Tests that
        // specifically exercise Stripe Connect rebind PaymentGateway (or
        // StripeClient) themselves.
        $this->app->bind(PaymentGateway::class, StubPaymentGateway::class);
    }
}
