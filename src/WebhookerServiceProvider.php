<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker;

use Illuminate\Support\ServiceProvider;
use TechraysLabs\Webhooker\Commands\EndpointListCommand;
use TechraysLabs\Webhooker\Commands\HealthCommand;
use TechraysLabs\Webhooker\Commands\PruneCommand;
use TechraysLabs\Webhooker\Commands\ReplayCommand;
use TechraysLabs\Webhooker\Contracts\InboundProcessor;
use TechraysLabs\Webhooker\Contracts\RetryStrategy;
use TechraysLabs\Webhooker\Contracts\SignatureGenerator;
use TechraysLabs\Webhooker\Contracts\WebhookMetrics;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;
use TechraysLabs\Webhooker\Services\DefaultInboundProcessor;
use TechraysLabs\Webhooker\Services\EloquentWebhookMetrics;
use TechraysLabs\Webhooker\Services\EloquentWebhookRepository;
use TechraysLabs\Webhooker\Services\HmacSignatureGenerator;
use TechraysLabs\Webhooker\Strategies\ExponentialBackoffRetry;

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
        $this->app->bind(WebhookMetrics::class, EloquentWebhookMetrics::class);
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
                HealthCommand::class,
            ]);
        }
    }
}
