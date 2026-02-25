<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $endpoint_id
 * @property float $success_rate
 * @property float $average_response_time_ms
 * @property int $total_events
 * @property int $failed_events
 * @property string $status
 * @property \Illuminate\Support\Carbon $recorded_at
 */
class WebhookHealthSnapshot extends Model
{
    protected $table = 'webhook_health_snapshots';

    public $timestamps = false;

    protected $fillable = [
        'endpoint_id',
        'success_rate',
        'average_response_time_ms',
        'total_events',
        'failed_events',
        'status',
        'recorded_at',
    ];

    protected $casts = [
        'success_rate' => 'float',
        'average_response_time_ms' => 'float',
        'total_events' => 'integer',
        'failed_events' => 'integer',
        'recorded_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id');
    }
}
