<?php

declare(strict_types=1);

namespace UniRate\Statamic\Tests;

use Illuminate\Support\Facades\Http;
use UniRate\Statamic\UniRateClient;
use UniRate\Statamic\UniRateException;

final class UniRateClientTest extends TestCase
{
    private function client(): UniRateClient
    {
        return app(UniRateClient::class);
    }

    // ---------------------------------------------------------------
    // getRate()
    // ---------------------------------------------------------------

    public function test_get_rate_single_returns_float(): void
    {
        Http::fake(['*/api/rates*' => Http::response(['rate' => '0.92'], 200)]);

        $this->assertSame(0.92, $this->client()->getRate('USD', 'EUR'));
    }

    public function test_get_rate_same_currency_returns_one_without_request(): void
    {
        Http::fake();

        $this->assertSame(1.0, $this->client()->getRate('USD', 'USD'));
        Http::assertNothingSent();
    }

    public function test_get_rate_uppercases_input(): void
    {
        Http::fake(['*/api/rates*' => Http::response(['rate' => '0.92'], 200)]);

        $this->client()->getRate('usd', 'eur');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'from=USD') && str_contains($r->url(), 'to=EUR'));
    }

    public function test_get_rate_without_to_returns_map(): void
    {
        Http::fake(['*/api/rates*' => Http::response(['rates' => ['EUR' => '0.92', 'GBP' => '0.79']], 200)]);

        $rates = $this->client()->getRate('USD');

        $this->assertIsArray($rates);
        $this->assertSame(0.92, $rates['EUR']);
        $this->assertSame(0.79, $rates['GBP']);
    }

    public function test_get_rate_throws_when_pair_missing(): void
    {
        Http::fake(['*/api/rates*' => Http::response([], 200)]);

        $this->expectException(UniRateException::class);
        $this->expectExceptionMessage('Currency pair USD/XYZ not available');

        $this->client()->getRate('USD', 'XYZ');
    }

    // ---------------------------------------------------------------
    // convert()
    // ---------------------------------------------------------------

    public function test_convert_returns_float(): void
    {
        Http::fake(['*/api/convert*' => Http::response(['result' => '92.50'], 200)]);

        $this->assertSame(92.5, $this->client()->convert('EUR', 100.0, 'USD'));
    }

    public function test_convert_sends_expected_params(): void
    {
        Http::fake(['*/api/convert*' => Http::response(['result' => '10'], 200)]);

        $this->client()->convert('eur', 5.0, 'usd');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'from=USD')
            && str_contains($r->url(), 'to=EUR')
            && str_contains($r->url(), 'amount=5'));
    }

    // ---------------------------------------------------------------
    // getSupportedCurrencies()
    // ---------------------------------------------------------------

    public function test_supported_currencies_returns_list(): void
    {
        Http::fake(['*/api/currencies*' => Http::response(['currencies' => ['USD', 'EUR', 'GBP']], 200)]);

        $currencies = $this->client()->getSupportedCurrencies();

        $this->assertSame(['USD', 'EUR', 'GBP'], $currencies);
    }

    // ---------------------------------------------------------------
    // getVatRates()
    // ---------------------------------------------------------------

    public function test_vat_single_country_returns_record(): void
    {
        Http::fake(['*/api/vat/rates*' => Http::response([
            'country' => 'DE',
            'vat_data' => ['country_code' => 'DE', 'country_name' => 'Germany', 'vat_rate' => 19.0],
        ], 200)]);

        $data = $this->client()->getVatRates('DE');

        $this->assertSame('Germany', $data['country_name']);
        $this->assertEquals(19.0, $data['vat_rate']);
    }

    public function test_vat_all_countries_returns_map(): void
    {
        Http::fake(['*/api/vat/rates*' => Http::response([
            'vat_rates' => [
                'DE' => ['country_code' => 'DE', 'country_name' => 'Germany', 'vat_rate' => 19.0],
                'FR' => ['country_code' => 'FR', 'country_name' => 'France', 'vat_rate' => 20.0],
            ],
        ], 200)]);

        $data = $this->client()->getVatRates();

        $this->assertArrayHasKey('DE', $data);
        $this->assertArrayHasKey('FR', $data);
    }

    // ---------------------------------------------------------------
    // Headers / auth
    // ---------------------------------------------------------------

    public function test_sends_api_key_query(): void
    {
        Http::fake(['*/api/rates*' => Http::response(['rates' => []], 200)]);

        $this->client()->getRate('USD');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'api_key=test-key'));
    }

    public function test_sends_accept_header(): void
    {
        Http::fake(['*/api/rates*' => Http::response(['rates' => []], 200)]);

        $this->client()->getRate('USD');

        Http::assertSent(fn ($r) => $r->header('Accept')[0] === 'application/json');
    }

    public function test_sends_user_agent(): void
    {
        Http::fake(['*/api/rates*' => Http::response(['rates' => []], 200)]);

        $this->client()->getRate('USD');

        Http::assertSent(fn ($r) => str_starts_with($r->header('User-Agent')[0], 'unirate-statamic/'));
    }

    // ---------------------------------------------------------------
    // Error mapping
    // ---------------------------------------------------------------

    public function test_400_throws_invalid_request(): void
    {
        Http::fake(['*/api/rates*' => Http::response('Bad Request', 400)]);

        $this->expectException(UniRateException::class);
        $this->expectExceptionMessage('Invalid request parameters');

        $this->client()->getRate('USD');
    }

    public function test_401_throws_authentication(): void
    {
        Http::fake(['*/api/rates*' => Http::response('Unauthorized', 401)]);

        $this->expectException(UniRateException::class);
        $this->expectExceptionMessage('Missing or invalid API key');

        $this->client()->getRate('USD');
    }

    public function test_403_throws_pro_required(): void
    {
        Http::fake(['*/api/historical/rates*' => Http::response('Forbidden', 403)]);

        $this->expectException(UniRateException::class);
        $this->expectExceptionMessage('Pro subscription');

        $this->client()->getHistoricalRate('2024-01-01', 1.0, 'USD', 'EUR');
    }

    public function test_404_throws_not_found(): void
    {
        Http::fake(['*/api/rates*' => Http::response('Not Found', 404)]);

        $this->expectException(UniRateException::class);
        $this->expectExceptionMessage('Currency not found');

        $this->client()->getRate('USD');
    }

    public function test_429_throws_rate_limit(): void
    {
        Http::fake(['*/api/rates*' => Http::response('Too Many Requests', 429)]);

        $this->expectException(UniRateException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $this->client()->getRate('USD');
    }

    public function test_503_throws_service_unavailable(): void
    {
        Http::fake(['*/api/rates*' => Http::response('Service Unavailable', 503)]);

        $this->expectException(UniRateException::class);
        $this->expectExceptionMessage('Service unavailable');

        $this->client()->getRate('USD');
    }

    public function test_500_throws_generic_api_error(): void
    {
        Http::fake(['*/api/rates*' => Http::response('boom', 500)]);

        $this->expectException(UniRateException::class);
        $this->expectExceptionMessage('API error (HTTP 500)');

        $this->client()->getRate('USD');
    }

    public function test_invalid_json_throws(): void
    {
        Http::fake(['*/api/rates*' => Http::response('not json', 200)]);

        $this->expectException(UniRateException::class);
        $this->expectExceptionMessage('Invalid API response');

        $this->client()->getRate('USD');
    }
}
