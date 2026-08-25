<?php

declare(strict_types=1);

namespace UniRate\Statamic\Tests;

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
    }
}
