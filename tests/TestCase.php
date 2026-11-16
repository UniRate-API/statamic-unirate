<?php

declare(strict_types=1);

namespace UniRate\Statamic\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Support\Facades\Http;
use Statamic\Testing\AddonTestCase;
use UniRate\Statamic\ServiceProvider;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('unirate.api_key', 'test-key');
        $app['config']->set('unirate.base_url', 'https://api.unirateapi.com');
        $app['config']->set('unirate.timeout', 5);
        $app['config']->set('unirate.cache_ttl', 0);
    }

    /**
     * Simulate a timeout the way Guzzle raises it: the message carries the full
     * request URL, including the api_key query parameter.
     */
    protected function fakeTimeout(): void
    {
        Http::fake(function ($request) {
            throw new ConnectException(
                'cURL error 28: Operation timed out after 5001 milliseconds with 0 bytes received (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for ' . $request->url(),
                new GuzzleRequest('GET', $request->url()),
            );
        });
    }
}
