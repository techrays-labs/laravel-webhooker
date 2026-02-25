<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $batch_id
 * @property string|null $event_name
 * @property int $total_events
 * @property int $successful_count
 * @property int $failed_count
 * @property int $pending_count
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class WebhookBatch extends Model
{
    protected $table = 'webhook_batches';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PARTIAL_FAILURE = 'partial_failure';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'batch_id',
        'event_name',
        'total_events',
        'successful_count',
        'failed_count',
        'pending_count',
        'status',
        'metadata',
        'completed_at',
    ];

    protected $casts = [
        'total_events' => 'integer',
        'successful_count' => 'integer',
        'failed_count' => 'integer',
        'pending_count' => 'integer',
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    /**
     * @return HasMany<WebhookEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(WebhookEvent::class, 'batch_id', 'batch_id');
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_PARTIAL_FAILURE, self::STATUS_FAILED]);
    }
}
