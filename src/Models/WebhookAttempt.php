<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $event_id
 * @property array<string, mixed>|null $request_headers
 * @property int|null $response_status
 * @property string|null $response_body
 * @property string|null $error_message
 * @property int $duration_ms
 * @property \Illuminate\Support\Carbon $attempted_at
 */
class WebhookAttempt extends Model
{
    public $timestamps = false;

    protected $table = 'webhook_attempts';

    protected $fillable = [
        'event_id',
        'request_headers',
        'response_status',
        'response_body',
        'error_message',
        'duration_ms',
        'attempted_at',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'response_status' => 'integer',
        'duration_ms' => 'integer',
        'attempted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<WebhookEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class, 'event_id');
    }

    /**
     * Determine if this attempt was successful (2xx status code).
     */
    public function isSuccessful(): bool
    {
        return $this->response_status !== null
            && $this->response_status >= 200
            && $this->response_status < 300;
    }
}
