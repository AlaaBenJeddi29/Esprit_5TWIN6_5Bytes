<?php

namespace Tests\Support;

use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;

/**
 * Client HTTP Stripe factice pour les tests : aucun appel réseau, aucune clé
 * réelle. Capture les paramètres envoyés pour les assertions.
 */
final class FakeStripeHttpClient implements ClientInterface
{
    /** @var list<array{method: string, absUrl: string, params: array<string, mixed>}> */
    public array $requests = [];

    public function __construct(
        private readonly ?string $connectionError = null,
        private readonly string $sessionId = 'cs_test_123',
        private readonly ?array $retrieveSession = null,
    ) {}

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = ['method' => $method, 'absUrl' => $absUrl, 'params' => $params];

        if ($this->connectionError !== null) {
            throw new ApiConnectionException($this->connectionError);
        }

        if (strtolower((string) $method) === 'get' && str_contains($absUrl, '/checkout/sessions/')) {
            $session = array_merge([
                'id' => $this->sessionId,
                'object' => 'checkout.session',
                'payment_status' => 'unpaid',
                'amount_total' => 0,
                'currency' => 'eur',
                'metadata' => (object) [],
            ], $this->retrieveSession ?? []);

            return [json_encode($session), 200, ['request-id' => 'req_test']];
        }

        $priceData = $params['line_items'][0]['price_data'] ?? [];

        return [json_encode([
            'id' => $this->sessionId,
            'object' => 'checkout.session',
            'url' => 'https://checkout.stripe.test/pay/'.$this->sessionId,
            'payment_status' => 'unpaid',
            'amount_total' => $priceData['unit_amount'] ?? 0,
            'currency' => $priceData['currency'] ?? 'eur',
        ]), 200, ['request-id' => 'req_test']];
    }
}
