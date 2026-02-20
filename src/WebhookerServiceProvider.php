<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker;

use Illuminate\Support\ServiceProvider;
use TechRaysLabs\Webhooker\Commands\EndpointListCommand;
use TechRaysLabs\Webhooker\Commands\PruneCommand;
use TechRaysLabs\Webhooker\Commands\ReplayCommand;
use TechRaysLabs\Webhooker\Contracts\InboundProcessor;
use TechRaysLabs\Webhooker\Contracts\RetryStrategy;
use TechRaysLabs\Webhooker\Contracts\SignatureGenerator;
use TechRaysLabs\Webhooker\Contracts\WebhookRepository;
use TechRaysLabs\Webhooker\Services\DefaultInboundProcessor;
use TechRaysLabs\Webhooker\Services\EloquentWebhookRepository;
use TechRaysLabs\Webhooker\Services\HmacSignatureGenerator;
use TechRaysLabs\Webhooker\Strategies\ExponentialBackoffRetry;

class WebhookerServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webhooks.php', 'webhooks');

        $this->app->bind(WebhookRepository::class, EloquentWebhookRepository::class);
        $this->app->bind(RetryStrategy::class, function ($app) {
            $config = $app['config']->get('webhooks.retry', []);

            return new ExponentialBackoffRetry(
                maxAttempts: $config['max_attempts'] ?? 5,
                baseDelaySeconds: $config['base_delay_seconds'] ?? 10,
                multiplier: $config['multiplier'] ?? 2,
            );
        });
        $this->app->bind(SignatureGenerator::class, HmacSignatureGenerator::class);
        $this->app->bind(InboundProcessor::class, DefaultInboundProcessor::class);
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app['config']->get('webhooks.dashboard.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webhooker');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/webhooks.php' => config_path('webhooks.php'),
            ], 'webhooker-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'webhooker-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/webhooker'),
            ], 'webhooker-views');

            $this->commands([
                PruneCommand::class,
                ReplayCommand::class,
                EndpointListCommand::class,
            ]);
        }
    }
}
