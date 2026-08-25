<?php

declare(strict_types=1);

namespace UniRate\Statamic;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Thin client for the UniRate API built on the Laravel HTTP client.
 *
 * Free-tier endpoints: rates, convert, currencies, vat.
 * Pro-gated endpoints (historical): tolerate HTTP 403.
 */
final class UniRateClient
{
    public const VERSION = '0.1.0';

    private readonly PendingRequest $http;

    public function __construct(
        private readonly string $apiKey,
        string $baseUrl,
        int $timeout,
        HttpFactory $httpFactory,
    ) {
        $this->http = $httpFactory
            ->baseUrl(rtrim($baseUrl, '/'))
            ->timeout($timeout)
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'unirate-statamic/' . self::VERSION,
            ]);
    }

    /**
     * Get the exchange rate between two currencies, or all rates for a base.
     *
     * @return float|array<string, float> single float when $to is set, else a map
     */
    public function getRate(string $from = 'USD', ?string $to = null): float|array
    {
        $from = strtoupper($from);
        $query = ['from' => $from];

        if ($to !== null) {
            $to = strtoupper($to);
            if ($from === $to) {
                return 1.0;
            }
            $query['to'] = $to;
        }

        $response = $this->request('/api/rates', $query);

        if ($to !== null) {
            if (! isset($response['rate'])) {
                throw new UniRateException("Currency pair {$from}/{$to} not available.");
            }

            return (float) $response['rate'];
        }

        $rates = [];
        foreach (($response['rates'] ?? []) as $code => $value) {
            $rates[(string) $code] = (float) $value;
        }

        return $rates;
    }

    /**
     * Convert an amount between two currencies.
     */
    public function convert(string $to, float $amount = 1.0, string $from = 'USD'): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        $response = $this->request('/api/convert', [
            'from' => $from,
            'to' => $to,
            'amount' => $amount,
        ]);

        if (! isset($response['result'])) {
            throw new UniRateException("Unable to convert {$from} to {$to}.");
        }

        return (float) $response['result'];
    }

    /**
     * List all supported currency codes.
     *
     * @return list<string>
     */
    public function getSupportedCurrencies(): array
    {
        $response = $this->request('/api/currencies', []);

        $currencies = [];
        foreach (($response['currencies'] ?? []) as $code) {
            $currencies[] = (string) $code;
        }

        return $currencies;
    }

    /**
     * Get VAT rates. Returns a single country's data if $country is set,
     * otherwise the full map of all countries.
     *
     * @return array<string, mixed>
     */
    public function getVatRates(?string $country = null): array
    {
        $query = [];
        if ($country !== null) {
            $query['country'] = strtoupper($country);
        }

        $response = $this->request('/api/vat/rates', $query);

        if ($country !== null) {
            return $response['vat_data'] ?? [];
        }

        return $response['vat_rates'] ?? [];
    }

    /**
     * Get a historical rate for a given date. Pro-gated — will throw a 403
     * UniRateException on the free tier.
     *
     * @return float|array<string, float>
     */
    public function getHistoricalRate(string $date, float $amount = 1.0, string $from = 'USD', ?string $to = null): float|array
    {
        $from = strtoupper($from);
        $query = ['date' => $date, 'from' => $from, 'amount' => $amount];

        if ($to !== null) {
            $query['to'] = strtoupper($to);
        }

        $response = $this->request('/api/historical/rates', $query);

        if ($to !== null) {
            if (isset($response['rate'])) {
                return (float) $response['rate'];
            }
            if (isset($response['result'])) {
                return (float) $response['result'];
            }
            throw new UniRateException("No historical data for {$from}/{$to} on {$date}.");
        }

        $map = [];
        foreach (($response['rates'] ?? $response['results'] ?? []) as $code => $value) {
            $map[(string) $code] = (float) $value;
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function request(string $path, array $query): array
    {
        $query['api_key'] = $this->apiKey;

        /** @var Response $response */
        $response = $this->http->get($path, $query);

        if ($response->successful()) {
            $data = $response->json();
            if (! is_array($data)) {
                throw new UniRateException(
                    'Invalid API response: expected JSON object, got: ' . substr((string) $response->body(), 0, 200),
                );
            }

            return $data;
        }

        $status = $response->status();
        $body = $response->body();

        throw match ($status) {
            400 => new UniRateException('Invalid request parameters.', $status),
            401 => new UniRateException('Missing or invalid API key.', $status),
            403 => new UniRateException('Endpoint requires a Pro subscription.', $status),
            404 => new UniRateException('Currency not found or no data available.', $status),
            429 => new UniRateException('Rate limit exceeded.', $status),
            503 => new UniRateException('Service unavailable.', $status),
            default => new UniRateException("API error (HTTP {$status}): {$body}", $status),
        };
    }
}
