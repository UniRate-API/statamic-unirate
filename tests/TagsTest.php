<?php

declare(strict_types=1);

namespace UniRate\Statamic\Tests;

use Illuminate\Support\Facades\Http;
use Statamic\Facades\Antlers;

final class TagsTest extends TestCase
{
    private function render(string $template): string
    {
        // trusted=true so Antlers executes tags (untrusted user data has tag
        // execution disabled for security — the same as real template rendering).
        return (string) Antlers::parse($template, [], true);
    }

    public function test_rate_tag_renders_single_rate(): void
    {
        Http::fake(['*/api/rates*' => Http::response(['rate' => '0.92'], 200)]);

        $out = $this->render('{{ unirate:rate from="USD" to="EUR" }}');

        $this->assertSame('0.92', trim($out));
    }

    public function test_rate_tag_pair_exposes_rate_variable(): void
    {
        Http::fake(['*/api/rates*' => Http::response(['rate' => '0.92'], 200)]);

        $out = $this->render('{{ unirate:rate from="USD" to="EUR" }}Rate: {{ rate }}{{ /unirate:rate }}');

        $this->assertStringContainsString('Rate: 0.92', $out);
    }

    public function test_rate_tag_loops_full_map_in_pair(): void
    {
        Http::fake(['*/api/rates*' => Http::response(['rates' => ['EUR' => '0.92', 'GBP' => '0.79']], 200)]);

        $out = $this->render('{{ unirate:rate from="USD" }}{{ currency }}={{ rate }};{{ /unirate:rate }}');

        $this->assertStringContainsString('EUR=0.92;', $out);
        $this->assertStringContainsString('GBP=0.79;', $out);
    }

    public function test_convert_tag_renders_result(): void
    {
        Http::fake(['*/api/convert*' => Http::response(['result' => '92.50'], 200)]);

        $out = $this->render('{{ unirate:convert from="USD" to="EUR" amount="100" }}');

        $this->assertSame('92.5', trim($out));
    }

    public function test_currencies_tag_loops_codes(): void
    {
        Http::fake(['*/api/currencies*' => Http::response(['currencies' => ['USD', 'EUR']], 200)]);

        $out = $this->render('{{ unirate:currencies }}{{ code }} {{ /unirate:currencies }}');

        $this->assertStringContainsString('USD', $out);
        $this->assertStringContainsString('EUR', $out);
    }

    public function test_vat_tag_renders_single_country(): void
    {
        Http::fake(['*/api/vat/rates*' => Http::response([
            'country' => 'DE',
            'vat_data' => ['country_code' => 'DE', 'country_name' => 'Germany', 'vat_rate' => 19.0],
        ], 200)]);

        $out = $this->render('{{ unirate:vat country="DE" }}');

        $this->assertSame('19', trim($out));
    }

    public function test_rate_tag_fails_gracefully_on_error(): void
    {
        Http::fake(['*/api/rates*' => Http::response('Unauthorized', 401)]);

        $out = $this->render('{{ unirate:rate from="USD" to="EUR" }}');

        $this->assertSame('', trim($out));
    }

    public function test_rate_tag_pair_exposes_error_on_failure(): void
    {
        Http::fake(['*/api/rates*' => Http::response('Unauthorized', 401)]);

        $out = $this->render('{{ unirate:rate from="USD" to="EUR" }}{{ if error }}FAILED{{ /if }}{{ /unirate:rate }}');

        $this->assertStringContainsString('FAILED', $out);
    }

    public function test_every_single_tag_renders_empty_on_timeout(): void
    {
        $this->fakeTimeout();

        foreach ([
            '{{ unirate:rate from="USD" to="EUR" }}',
            '{{ unirate:convert from="USD" to="EUR" amount="100" }}',
            '{{ unirate:currencies }}',
            '{{ unirate:vat country="DE" }}',
        ] as $template) {
            $this->assertSame('', trim($this->render($template)), $template);
        }
    }

    public function test_every_tag_pair_exposes_safe_error_on_timeout(): void
    {
        $this->fakeTimeout();

        foreach ([
            '{{ unirate:rate from="USD" to="EUR" }}[{{ error }}]{{ /unirate:rate }}',
            '{{ unirate:convert from="USD" to="EUR" amount="100" }}[{{ error }}]{{ /unirate:convert }}',
            '{{ unirate:vat country="DE" }}[{{ error }}]{{ /unirate:vat }}',
        ] as $template) {
            $out = $this->render($template);
            $this->assertStringContainsString('[Could not reach the UniRate API', $out, $template);
            $this->assertStringNotContainsString('test-key', $out, $template);
            $this->assertStringNotContainsString('api_key', $out, $template);
        }
    }

    public function test_vat_tag_pair_renders_country_fields(): void
    {
        Http::fake(['*/api/vat/rates*' => Http::response([
            'country' => 'DE',
            'vat_data' => ['country_code' => 'DE', 'country_name' => 'Germany', 'vat_rate' => 19.0],
        ], 200)]);

        $out = $this->render('{{ unirate:vat country="DE" }}{{ country_name }}: {{ vat_rate }}%{{ /unirate:vat }}');

        $this->assertStringContainsString('Germany: 19%', $out);
    }

    public function test_convert_tag_pair_exposes_result(): void
    {
        Http::fake(['*/api/convert*' => Http::response(['result' => '92.50'], 200)]);

        $out = $this->render('{{ unirate:convert from="USD" to="EUR" amount="100" }}= {{ result }}{{ /unirate:convert }}');

        $this->assertStringContainsString('= 92.5', $out);
    }

    public function test_currencies_single_tag_renders_comma_list(): void
    {
        Http::fake(['*/api/currencies*' => Http::response(['currencies' => ['USD', 'EUR']], 200)]);

        $this->assertSame('USD, EUR', trim($this->render('{{ unirate:currencies }}')));
    }

    public function test_self_closing_single_tag_coexists_with_pair_of_same_tag(): void
    {
        Http::fake(['*/api/rates*' => function ($request) {
            return Http::response(str_contains($request->url(), 'to=GBP') ? ['rate' => '0.79'] : ['rate' => '0.92'], 200);
        }]);

        $out = $this->render(
            'A=[{{ unirate:rate from="USD" to="EUR" /}}] '
            . 'B=[{{ unirate:rate from="USD" to="GBP" }}{{ if error }}ERR{{ else }}{{ rate }}{{ /if }}{{ /unirate:rate }}]'
        );

        $this->assertStringContainsString('A=[0.92]', $out);
        $this->assertStringContainsString('B=[0.79]', $out);
    }
}
