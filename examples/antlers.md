# UniRate Antlers examples

Drop these snippets into any Antlers template once the addon is installed and
your API key is set (`UNIRATE_API_KEY` in `.env`).

Single (non-pair) tags use the self-closing `/}}` form. Antlers pairs an opening
tag with the next matching closing tag, so a plain `{{ unirate:rate ... }}`
followed later by a `{{ unirate:rate }}...{{ /unirate:rate }}` pair would be
mis-paired.

## Publish the config (optional)

```bash
php artisan vendor:publish --tag=unirate-config
```

This copies `config/unirate.php` into your app so you can tweak `base_url`,
`timeout` and `cache_ttl`. The API key is read from `UNIRATE_API_KEY` by default.

## Single exchange rate

```antlers
1 USD = {{ unirate:rate from="USD" to="EUR" /}} EUR
```

## Rate in a tag pair (exposes {{ rate }} and {{ error }})

```antlers
{{ unirate:rate from="USD" to="GBP" }}
    {{ if error }}
        Rates are unavailable right now.
    {{ else }}
        Today's rate: {{ rate }}
    {{ /if }}
{{ /unirate:rate }}
```

## Loop every rate for a base currency

```antlers
<ul>
{{ unirate:rate from="USD" }}
    <li>{{ currency }}: {{ rate }}</li>
{{ /unirate:rate }}
</ul>
```

## Convert an amount

```antlers
$100 USD is €{{ unirate:convert from="USD" to="EUR" amount="100" /}}
```

## List supported currencies

```antlers
{{ unirate:currencies }}<option value="{{ code }}">{{ code }}</option>{{ /unirate:currencies }}
```

Or as a single inline comma-separated list:

```antlers
Supported: {{ unirate:currencies /}}
```

## VAT rate for a country

```antlers
{{ unirate:vat country="DE" }}
    {{ country_name }} VAT: {{ vat_rate }}%
{{ /unirate:vat }}
```

Or inline (renders just the numeric rate):

```antlers
German VAT is {{ unirate:vat country="DE" /}}%
```
