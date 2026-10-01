<?php

namespace Omnibus\Amazon;

use Omnibus\Exception\CarrierException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Amazon Shipping through the Selling Partner API (shipping v2): a Login
 * with Amazon refresh token exchanged for an access token, then the
 * x-amz-access-token header on sellingpartnerapi-<region>.amazon.com.
 */
final class Api
{
    public const LWA = 'https://api.amazon.com/auth/o2/token';
    public const ENDPOINTS = ['eu' => 'https://sellingpartnerapi-eu.amazon.com', 'na' => 'https://sellingpartnerapi-na.amazon.com', 'fe' => 'https://sellingpartnerapi-fe.amazon.com'];

    private ?string $token = null;
    private int $expiresAt = 0;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $refreshToken,
        public readonly string $region = 'eu',
        public readonly ?string $businessId = null,
        public readonly bool $sandbox = false,
        private readonly int $timeout = 20,
    ) {
    }

    public function base(): string
    {
        $base = self::ENDPOINTS[$this->region] ?? self::ENDPOINTS['eu'];

        return $this->sandbox ? str_replace('sellingpartnerapi-', 'sandbox.sellingpartnerapi-', $base) : $base;
    }

    /** @return array<string, mixed> the payload */
    public function call(string $method, string $path, ?array $body = null, array $query = []): array
    {
        try {
            $response = $this->http->request($method, $this->base().$path, [
                'headers' => array_filter(['x-amz-access-token' => $this->token(), 'x-amzn-shipping-business-id' => $this->businessId, 'Content-Type' => 'application/json', 'Accept' => 'application/json']),
                'query' => $query,
                'body' => null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR),
                'timeout' => $this->timeout,
            ]);
            $status = $response->getStatusCode();
            $data = json_decode($response->getContent(false), true);
        } catch (HttpExceptionInterface|\JsonException $e) {
            throw new CarrierException('amazon', 'Amazon Shipping request failed: '.$e->getMessage(), null, $e);
        }
        if (!\is_array($data)) {
            throw new CarrierException('amazon', sprintf('Amazon Shipping answered HTTP %d with a body that is not JSON.', $status));
        }
        if ($status >= 400 || !empty($data['errors'])) {
            $error = $data['errors'][0] ?? [];
            throw new CarrierException('amazon', (string) ($error['message'] ?? sprintf('HTTP %d', $status)), isset($error['code']) ? (string) $error['code'] : null);
        }

        return $data['payload'] ?? $data;
    }

    private function token(): string
    {
        if (null !== $this->token && time() < $this->expiresAt - 60) {
            return $this->token;
        }
        try {
            $data = $this->http->request('POST', self::LWA, ['body' => ['grant_type' => 'refresh_token', 'refresh_token' => $this->refreshToken, 'client_id' => $this->clientId, 'client_secret' => $this->clientSecret], 'timeout' => $this->timeout])->toArray(false);
        } catch (HttpExceptionInterface $e) {
            throw new CarrierException('amazon', 'Login with Amazon gave no token: '.$e->getMessage(), null, $e);
        }
        if (empty($data['access_token'])) {
            throw new CarrierException('amazon', (string) ($data['error_description'] ?? 'Login with Amazon gave no token: check the client id, secret and refresh token.'));
        }
        $this->token = (string) $data['access_token'];
        $this->expiresAt = time() + (int) ($data['expires_in'] ?? 3600);

        return $this->token;
    }
}
