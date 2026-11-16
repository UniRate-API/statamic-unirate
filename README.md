# UniRate for Statamic

A [Statamic](https://statamic.com) addon that brings live currency exchange
rates, conversion, supported-currency lists, and VAT rates into your Antlers
templates — powered by the [UniRate API](https://unirateapi.com).

**Compatibility:** Statamic 5 and 6 (PHP 8.2+). CI runs the test suite against
both majors.

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
{{# Single rate. Use the self-closing /}} form for single tags, so Antlers #}}
{{# never pairs them with a later {{ /unirate:rate }} closing tag.        #}}
1 USD = {{ unirate:rate from="USD" to="EUR" /}} EUR

{{# As a pair, with graceful error handling #}}
{{ unirate:rate from="USD" to="GBP" }}
    {{ if error }}Unavailable{{ else }}{{ rate }}{{ /if }}
{{ /unirate:rate }}

{{# Loop every rate for a base currency #}}
{{ unirate:rate from="USD" }}
    {{ currency }}: {{ rate }}
{{ /unirate:rate }}
```

### `{{ unirate:convert }}`

```antlers
$100 USD ≈ €{{ unirate:convert from="USD" to="EUR" amount="100" /}}
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

### Failing gracefully

Tags never break the page. On any failure — an HTTP error, a timeout, or the
API being unreachable — a single tag renders nothing, and a tag pair renders its
contents once with an `{{ error }}` variable so you can present a fallback:

```antlers
{{ unirate:currencies }}
    {{ if error }}
        <option disabled>Currencies unavailable</option>
    {{ else }}
        <option value="{{ code }}">{{ code }}</option>
    {{ /if }}
{{ /unirate:currencies }}
```

Error messages never contain the request URL or your API key. After a
connection failure the addon stops calling the API for 30 seconds, so an outage
costs one timeout per page instead of one per tag.

### Caching

Successful responses are cached in your default cache store for 10 minutes
(`UNIRATE_CACHE_TTL`, in seconds) so pages don't call the API on every render.
Set `UNIRATE_CACHE_TTL=0` to disable. Failed requests are never cached.

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
| `timeout` | `UNIRATE_TIMEOUT` | `5` (seconds) |
| `cache_ttl` | `UNIRATE_CACHE_TTL` | `600` (seconds, `0` = off) |

## Error handling

The client throws `UniRate\Statamic\UniRateException` for every failure,
including timeouts and connection errors (code `0`). HTTP errors carry the
status as the exception code:

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

## Support

Found a bug or need help? Open an issue on
[GitHub Issues](https://github.com/UniRate-API/statamic-unirate/issues). For
questions about your API key or plan, see [unirateapi.com](https://unirateapi.com).

## License

MIT — see [LICENSE](LICENSE).

## Related clients

Part of the official UniRate client family: Python, Node/TypeScript, Swift,
Java, Go, Rust, Ruby, PHP, and .NET, plus framework integrations for Laravel,
Symfony, WordPress, and more.
