<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $url
 * @property string $direction
 * @property string $secret
 * @property bool $is_active
 * @property int $timeout_seconds
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class WebhookEndpoint extends Model
{
    protected $table = 'webhook_endpoints';

    protected $fillable = [
        'name',
        'url',
        'direction',
        'secret',
        'is_active',
        'timeout_seconds',
    ];

    protected $hidden = [
        'secret',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'timeout_seconds' => 'integer',
    ];

    /**
     * @return HasMany<WebhookEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(WebhookEvent::class, 'endpoint_id');
    }

    /**
     * Determine if this endpoint handles outbound webhooks.
     */
    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    /**
     * Determine if this endpoint handles inbound webhooks.
     */
    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }
}
