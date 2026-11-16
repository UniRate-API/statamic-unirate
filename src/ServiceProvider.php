<?php

declare(strict_types=1);

namespace UniRate\Statamic;

use Illuminate\Http\Client\Factory as HttpFactory;
use Statamic\Providers\AddonServiceProvider;
use UniRate\Statamic\Tags\UniRateTags;

class ServiceProvider extends AddonServiceProvider
{
    /**
     * Antlers tags provided by this addon.
     *
     * @var array<int, class-string>
     */
    protected $tags = [
        UniRateTags::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__ . '/../config/unirate.php', 'unirate');

        $this->app->singleton(UniRateClient::class, function ($app) {
            $config = $app['config']['unirate'];

            return new UniRateClient(
                apiKey: (string) ($config['api_key'] ?? ''),
                baseUrl: (string) ($config['base_url'] ?? 'https://api.unirateapi.com'),
                timeout: (int) ($config['timeout'] ?? 5),
                httpFactory: $app->make(HttpFactory::class),
                cache: $app['cache']->store(),
                cacheTtl: max(0, (int) ($config['cache_ttl'] ?? 0)),
            );
        });

        $this->app->alias(UniRateClient::class, 'unirate');
    }

    public function bootAddon(): void
    {
        $this->publishes([
            __DIR__ . '/../config/unirate.php' => config_path('unirate.php'),
        ], 'unirate-config');
    }
}
