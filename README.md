# UniRate for Statamic

A [Statamic v5](https://statamic.com) addon that brings live currency exchange
rates, conversion, supported-currency lists, and VAT rates into your Antlers
templates — powered by the [UniRate API](https://unirateapi.com).

## Installation

```bash
composer require unirate/statamic
```

The addon's service provider is auto-discovered. Publish the config if you want
to override the defaults:

```bash
php artisan vendor:publish --tag=unirate-config
```

Set your API key in `.env`:

```env
UNIRATE_API_KEY=your-key-here
```

Get a free API key at [unirateapi.com](https://unirateapi.com).

## Antlers tags

### `{{ unirate:rate }}`

```antlers
{{-- Single rate --}}
1 USD = {{ unirate:rate from="USD" to="EUR" }} EUR

{{-- As a pair, with graceful error handling --}}
{{ unirate:rate from="USD" to="GBP" }}
    {{ if error }}Unavailable{{ else }}{{ rate }}{{ /if }}
{{ /unirate:rate }}

{{-- Loop every rate for a base currency --}}
{{ unirate:rate from="USD" }}
    {{ currency }}: {{ rate }}
{{ /unirate:rate }}
```

### `{{ unirate:convert }}`

```antlers
$100 USD ≈ €{{ unirate:convert from="USD" to="EUR" amount="100" }}
```

### `{{ unirate:currencies }}`

```antlers
{{ unirate:currencies }}
    <option value="{{ code }}">{{ code }}</option>
{{ /unirate:currencies }}
```

### `{{ unirate:vat }}`

```antlers
{{ unirate:vat country="DE" }}
    {{ country_name }} VAT: {{ vat_rate }}%
{{ /unirate:vat }}
```

All tags **fail gracefully**: on any API error a single tag renders nothing, and
a tag pair exposes an `{{ error }}` variable so you can present a fallback.

## Using the client directly

The addon registers a singleton `UniRate\Statamic\UniRateClient` you can inject
or resolve in your own code:

```php
use UniRate\Statamic\UniRateClient;

$client = app(UniRateClient::class);

$rate       = $client->getRate('USD', 'EUR');       // 0.92
$allRates   = $client->getRate('USD');              // ['EUR' => 0.92, ...]
$converted  = $client->convert('EUR', 100, 'USD');  // 92.5
$currencies = $client->getSupportedCurrencies();    // ['USD', 'EUR', ...]
$vat        = $client->getVatRates('DE');           // ['country_name' => 'Germany', 'vat_rate' => 19.0]
```

## Configuration

`config/unirate.php`:

| Key | Env | Default |
|---|---|---|
| `api_key` | `UNIRATE_API_KEY` | — |
| `base_url` | `UNIRATE_API_BASE_URL` | `https://api.unirateapi.com` |
| `timeout` | `UNIRATE_TIMEOUT` | `10` |

## Error handling

The client throws `UniRate\Statamic\UniRateException` with the HTTP status as the
exception code:

| Status | Meaning |
|---|---|
| 400 | Invalid request parameters |
| 401 | Missing or invalid API key |
| 403 | Endpoint requires a Pro subscription |
| 404 | Currency not found or no data available |
| 429 | Rate limit exceeded |
| 503 | Service unavailable |

Historical endpoints (`getHistoricalRate`) are Pro-gated and return **403** on
the free tier.

## Testing

```bash
composer install
./vendor/bin/phpunit
```

The suite mocks all HTTP traffic — no live API calls or key required.

## License

MIT — see [LICENSE](LICENSE).

## Related clients

Part of the official UniRate client family: Python, Node/TypeScript, Swift,
Java, Go, Rust, Ruby, PHP, and .NET, plus framework integrations for Laravel,
Symfony, WordPress, and more.
