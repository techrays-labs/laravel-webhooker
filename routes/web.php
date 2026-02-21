<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use TechraysLabs\Webhooker\Http\Controllers\DashboardController;
use TechraysLabs\Webhooker\Http\Controllers\InboundWebhookController;
use TechraysLabs\Webhooker\Http\Middleware\CheckIpAllowlist;
use TechraysLabs\Webhooker\Http\Middleware\VerifyWebhookSignature;

$dashboardConfig = config('webhooks.dashboard', []);
$prefix = $dashboardConfig['prefix'] ?? 'webhooks';
$middleware = $dashboardConfig['middleware'] ?? ['web', 'auth'];
$gate = $dashboardConfig['gate'] ?? 'viewWebhookDashboard';

// Dashboard routes
Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () use ($gate) {
        Route::get('/', [DashboardController::class, 'events'])
            ->can($gate)
            ->name('webhooker.events.index');

        Route::get('/events/{event}', [DashboardController::class, 'showEvent'])
            ->can($gate)
            ->name('webhooker.events.show');

        Route::get('/endpoints', [DashboardController::class, 'endpoints'])
            ->can($gate)
            ->name('webhooker.endpoints.index');

        Route::get('/endpoints/{endpoint}', [DashboardController::class, 'showEndpoint'])
            ->can($gate)
            ->name('webhooker.endpoints.show');

        Route::post('/events/bulk', [DashboardController::class, 'bulkAction'])
            ->can($gate)
            ->name('webhooker.events.bulk');
    });

// Inbound webhook receiver route (no auth, uses signature verification)
Route::post('/api/webhooks/inbound/{endpoint}', InboundWebhookController::class)
    ->where('endpoint', 'ep_[a-zA-Z0-9]+')
    ->middleware([CheckIpAllowlist::class, VerifyWebhookSignature::class])
    ->name('webhooker.inbound');
