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
 * @property string|null $idempotency_key
 * @property string|null $batch_id
 * @property string|null $dead_letter_reason
 * @property \Illuminate\Support\Carbon|null $dead_lettered_at
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
        'idempotency_key',
        'batch_id',
        'status',
        'attempts_count',
        'last_attempt_at',
        'next_retry_at',
        'dead_letter_reason',
        'dead_lettered_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts_count' => 'integer',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'dead_lettered_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DEAD_LETTER = 'dead_letter';

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

    /**
     * Determine if the event is in the dead-letter queue.
     */
    public function isDeadLetter(): bool
    {
        return $this->status === self::STATUS_DEAD_LETTER;
    }

    /**
     * @return BelongsTo<WebhookBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(WebhookBatch::class, 'batch_id', 'batch_id');
    }
}
