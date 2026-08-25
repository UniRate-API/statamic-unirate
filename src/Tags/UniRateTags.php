<?php

declare(strict_types=1);

namespace UniRate\Statamic\Tags;

use Statamic\Tags\Tags;
use UniRate\Statamic\UniRateClient;
use UniRate\Statamic\UniRateException;

/**
 * Antlers tags for the UniRate API. Registered under the `unirate` handle, so
 * each public method maps to a sub-tag:
 *
 *   {{ unirate:rate from="USD" to="EUR" }}
 *   {{ unirate:convert from="USD" to="EUR" amount="100" }}
 *   {{ unirate:currencies }}
 *   {{ unirate:vat country="DE" }}
 *
 * Every tag fails gracefully: a single tag renders nothing on error, a tag pair
 * exposes an {{ error }} variable so templates can present a fallback.
 */
class UniRateTags extends Tags
{
    protected static $handle = 'unirate';

    /**
     * {{ unirate:rate from="USD" to="EUR" }}                      → 0.92
     * {{ unirate:rate from="USD" }}{{ currency }}:{{ rate }}{{ /unirate:rate }}
     */
    public function rate(): mixed
    {
        $from = (string) ($this->params->get('from', 'USD'));
        $to = $this->params->get('to');

        try {
            $result = $this->client()->getRate($from, $to !== null ? (string) $to : null);
        } catch (UniRateException $e) {
            return $this->fail($e, ['rate' => null]);
        }

        if (is_float($result)) {
            if ($this->isPair) {
                return $this->parse(['error' => null, 'rate' => $result]);
            }

            return (string) $result;
        }

        $rows = [];
        foreach ($result as $code => $value) {
            $rows[] = ['currency' => $code, 'rate' => $value];
        }

        return $this->isPair ? $rows : '';
    }

    /**
     * {{ unirate:convert from="USD" to="EUR" amount="100" }}   → 92.5
     */
    public function convert(): mixed
    {
        $from = (string) ($this->params->get('from', 'USD'));
        $to = (string) ($this->params->get('to', ''));
        $amount = (float) ($this->params->get('amount', 1));

        try {
            $result = $this->client()->convert($to, $amount, $from);
        } catch (UniRateException $e) {
            return $this->fail($e, ['result' => null]);
        }

        if ($this->isPair) {
            return $this->parse(['error' => null, 'result' => $result]);
        }

        return (string) $result;
    }

    /**
     * {{ unirate:currencies }}{{ code }} {{ /unirate:currencies }}
     */
    public function currencies(): mixed
    {
        try {
            $currencies = $this->client()->getSupportedCurrencies();
        } catch (UniRateException $e) {
            return $this->fail($e, ['currencies' => []]);
        }

        if ($this->isPair) {
            return array_map(fn ($code) => ['code' => $code], $currencies);
        }

        return implode(', ', $currencies);
    }

    /**
     * {{ unirate:vat country="DE" }}   → 19
     * {{ unirate:vat country="DE" }}{{ country_name }}: {{ vat_rate }}{{ /unirate:vat }}
     * {{ unirate:vat }}{{ country_name }}: {{ vat_rate }}{{ /unirate:vat }}  (all countries)
     */
    public function vat(): mixed
    {
        $country = $this->params->get('country');

        try {
            $data = $this->client()->getVatRates($country !== null ? (string) $country : null);
        } catch (UniRateException $e) {
            return $this->fail($e, ['vat_rate' => null]);
        }

        if ($country !== null) {
            if ($this->isPair) {
                return $this->parse(array_merge(['error' => null], $data));
            }

            return isset($data['vat_rate']) ? (string) $data['vat_rate'] : '';
        }

        $rows = [];
        foreach ($data as $code => $record) {
            $rows[] = is_array($record) ? $record : ['country_code' => $code, 'vat_rate' => $record];
        }

        return $this->isPair ? $rows : '';
    }

    private function client(): UniRateClient
    {
        return app(UniRateClient::class);
    }

    /**
     * Render a graceful failure: nothing for a single tag, {{ error }} for a pair.
     *
     * @param  array<string, mixed>  $extra
     */
    private function fail(UniRateException $e, array $extra = []): mixed
    {
        return $this->isPair
            ? $this->parse(array_merge(['error' => $e->getMessage()], $extra))
            : '';
    }
}
