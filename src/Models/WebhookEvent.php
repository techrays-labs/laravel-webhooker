<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $endpoint_id
 * @property string $event_name
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts_count
 * @property \Illuminate\Support\Carbon|null $last_attempt_at
 * @property \Illuminate\Support\Carbon|null $next_retry_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class WebhookEvent extends Model
{
    protected $table = 'webhook_events';

    protected $fillable = [
        'endpoint_id',
        'event_name',
        'payload',
        'status',
        'attempts_count',
        'last_attempt_at',
        'next_retry_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts_count' => 'integer',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id');
    }

    /**
     * @return HasMany<WebhookAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(WebhookAttempt::class, 'event_id');
    }

    /**
     * Determine if the event has been delivered successfully.
     */
    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    /**
     * Determine if the event has permanently failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Determine if the event is still pending delivery.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
