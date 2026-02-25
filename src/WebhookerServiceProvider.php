<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker;

use Illuminate\Support\ServiceProvider;
use TechraysLabs\Webhooker\Commands\CircuitResetCommand;
use TechraysLabs\Webhooker\Commands\CircuitStatusCommand;
use TechraysLabs\Webhooker\Commands\CleanExpiredSecretsCommand;
use TechraysLabs\Webhooker\Commands\DeadLetterCommand;
use TechraysLabs\Webhooker\Commands\EndpointDisableCommand;
use TechraysLabs\Webhooker\Commands\EndpointEnableCommand;
use TechraysLabs\Webhooker\Commands\EndpointListCommand;
use TechraysLabs\Webhooker\Commands\HealthCommand;
use TechraysLabs\Webhooker\Commands\HealthSnapshotCommand;
use TechraysLabs\Webhooker\Commands\PartitionCreateCommand;
use TechraysLabs\Webhooker\Commands\PartitionDropCommand;
use TechraysLabs\Webhooker\Commands\PruneCommand;
use TechraysLabs\Webhooker\Commands\ReplayCommand;
use TechraysLabs\Webhooker\Commands\SecretRotateCommand;
use TechraysLabs\Webhooker\Commands\SimulateCommand;
use TechraysLabs\Webhooker\Contracts\CircuitBreaker;
use TechraysLabs\Webhooker\Contracts\InboundProcessor;
use TechraysLabs\Webhooker\Contracts\PayloadValidator;
use TechraysLabs\Webhooker\Contracts\RetryStrategy;
use TechraysLabs\Webhooker\Contracts\SignatureGenerator;
use TechraysLabs\Webhooker\Contracts\WebhookLock;
use TechraysLabs\Webhooker\Contracts\WebhookMetrics;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;
use TechraysLabs\Webhooker\Services\CacheCircuitBreaker;
use TechraysLabs\Webhooker\Services\CacheLockProvider;
use TechraysLabs\Webhooker\Services\ConfigPayloadValidator;
use TechraysLabs\Webhooker\Services\DefaultInboundProcessor;
use TechraysLabs\Webhooker\Services\EloquentWebhookMetrics;
use TechraysLabs\Webhooker\Services\HmacSignatureGenerator;
use TechraysLabs\Webhooker\Storage\PartitionManager;
use TechraysLabs\Webhooker\Storage\WebhookStorageManager;
use TechraysLabs\Webhooker\Strategies\ExponentialBackoffRetry;

class WebhookerServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webhooks.php', 'webhooks');

        // Storage driver manager (resolves WebhookRepository via driver pattern)
        $this->app->singleton(WebhookStorageManager::class, function ($app) {
            return new WebhookStorageManager($app);
        });

        $this->app->bind(WebhookRepository::class, function ($app) {
            return $app->make(WebhookStorageManager::class)->driver();
        });

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
        $this->app->bind(CircuitBreaker::class, CacheCircuitBreaker::class);
        $this->app->bind(PayloadValidator::class, ConfigPayloadValidator::class);

        // Distributed locking for horizontal scaling
        $this->app->bind(WebhookLock::class, CacheLockProvider::class);

        // Partition manager
        $this->app->singleton(PartitionManager::class);
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->warnIfDebugInProduction();

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

            $this->publishes([
                __DIR__.'/../database/stubs' => database_path('stubs/webhooker'),
            ], 'webhooker-stubs');

            $this->commands([
                PruneCommand::class,
                ReplayCommand::class,
                EndpointListCommand::class,
                HealthCommand::class,
                CircuitStatusCommand::class,
                CircuitResetCommand::class,
                EndpointDisableCommand::class,
                EndpointEnableCommand::class,
                SimulateCommand::class,
                SecretRotateCommand::class,
                CleanExpiredSecretsCommand::class,
                DeadLetterCommand::class,
                HealthSnapshotCommand::class,
                PartitionCreateCommand::class,
                PartitionDropCommand::class,
            ]);
        }
    }

    /**
     * Log a warning if webhook debug mode is enabled in a production environment.
     */
    private function warnIfDebugInProduction(): void
    {
        if ($this->app['config']->get('webhooks.debug.enabled', false)
            && $this->app->environment('production')) {
            $logger = new \TechraysLabs\Webhooker\Support\WebhookLogger;
            $logger->warning('Webhook debug mode is enabled in production. This should be disabled for security and performance.');
        }
    }
}
