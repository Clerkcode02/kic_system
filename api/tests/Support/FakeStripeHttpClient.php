<?php

declare(strict_types=1);

namespace Tests\Support;

use Stripe\HttpClient\ClientInterface;

/**
 * Stripe's own recommended test seam: a fake HttpClient installed via
 * \Stripe\ApiRequestor::setHttpClient(), so StripeClient/StripePaymentService/
 * StripeConnectService run their real request-building code and we assert
 * on the exact params sent — no network call, no magic-getter mocking.
 */
final class FakeStripeHttpClient implements ClientInterface
{
    /** @var list<array{method: string, absUrl: string, params: array}> */
    public array $requests = [];

    /**
     * @param  list<array{body: array, code?: int}>  $responses  consumed in order, one per request() call
     */
    public function __construct(private array $responses)
    {
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = ['method' => $method, 'absUrl' => $absUrl, 'params' => $params];

        $response = array_shift($this->responses) ?? ['body' => [], 'code' => 200];

        return [json_encode($response['body']), $response['code'] ?? 200, []];
    }
}
