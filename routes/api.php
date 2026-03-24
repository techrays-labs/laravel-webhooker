<?php

use Illuminate\Support\Facades\Route;
use TechraysLabs\Webhooker\Http\Controllers\Api\WebhookEndpointController;
use TechraysLabs\Webhooker\Http\Controllers\Api\WebhookEventController;
use TechraysLabs\Webhooker\Http\Controllers\Api\WebhookApiTokenController;
use TechraysLabs\Webhooker\Http\Controllers\Api\WebhookAnalyticsController;
use TechraysLabs\Webhooker\Http\Controllers\Api\WebhookEventSchemaController;

Route::prefix('webhooks')->middleware('webhook.api')->group(function () {
    Route::get('/health', fn () => response()->json(['status' => 'ok', 'version' => '1.0.0']));

    Route::get('/endpoints', [WebhookEndpointController::class, 'index']);
    Route::post('/endpoints', [WebhookEndpointController::class, 'store']);
    Route::get('/endpoints/{endpoint}', [WebhookEndpointController::class, 'show']);
    Route::put('/endpoints/{endpoint}', [WebhookEndpointController::class, 'update']);
    Route::delete('/endpoints/{endpoint}', [WebhookEndpointController::class, 'destroy']);
    Route::post('/endpoints/{endpoint}/enable', [WebhookEndpointController::class, 'enable']);
    Route::post('/endpoints/{endpoint}/disable', [WebhookEndpointController::class, 'disable']);

    Route::get('/events', [WebhookEventController::class, 'index']);
    Route::get('/events/{event}', [WebhookEventController::class, 'show']);
    Route::post('/events/{event}/replay', [WebhookEventController::class, 'replay']);
    Route::post('/events/{event}/retry', [WebhookEventController::class, 'retry']);
    Route::delete('/events/{event}', [WebhookEventController::class, 'destroy']);

    Route::get('/analytics/overview', [WebhookAnalyticsController::class, 'overview']);
    Route::get('/analytics/endpoints/{endpoint}', [WebhookAnalyticsController::class, 'endpointStats']);

    Route::get('/schemas', [WebhookEventSchemaController::class, 'index']);
    Route::post('/schemas', [WebhookEventSchemaController::class, 'store']);
    Route::get('/schemas/{schema}', [WebhookEventSchemaController::class, 'show']);
    Route::put('/schemas/{schema}', [WebhookEventSchemaController::class, 'update']);
    Route::delete('/schemas/{schema}', [WebhookEventSchemaController::class, 'destroy']);
    Route::post('/schemas/{eventName}/validate', [WebhookEventSchemaController::class, 'validate']);

    Route::middleware('ability:token:write')->group(function () {
        Route::get('/tokens', [WebhookApiTokenController::class, 'index']);
        Route::post('/tokens', [WebhookApiTokenController::class, 'store']);
        Route::put('/tokens/{token}', [WebhookApiTokenController::class, 'update']);
        Route::delete('/tokens/{token}', [WebhookApiTokenController::class, 'destroy']);
    });
});
