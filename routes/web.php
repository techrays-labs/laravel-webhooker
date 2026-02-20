<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use TechRaysLabs\Webhooker\Http\Controllers\DashboardController;
use TechRaysLabs\Webhooker\Http\Controllers\InboundWebhookController;
use TechRaysLabs\Webhooker\Http\Middleware\VerifyWebhookSignature;

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
    });

// Inbound webhook receiver route (no auth, uses signature verification)
Route::post('/api/webhooks/inbound/{endpoint}', InboundWebhookController::class)
    ->middleware(VerifyWebhookSignature::class)
    ->name('webhooker.inbound');
