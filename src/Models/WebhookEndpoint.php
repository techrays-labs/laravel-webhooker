<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use TechraysLabs\Webhooker\Contracts\WebhookMetrics;
use TechraysLabs\Webhooker\DTOs\EndpointHealth;

/**
 * @property int $id
 * @property string $route_token
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
        'route_token',
    ];

    protected $hidden = [
        'secret',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'timeout_seconds' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebhookEndpoint $endpoint): void {
            if (empty($endpoint->route_token)) {
                $endpoint->route_token = 'ep_'.Str::random(12);
            }
        });
    }

    /**
     * Get the route key name for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'route_token';
    }

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

    /**
     * Get the computed health status for this endpoint.
     */
    public function healthStatus(): EndpointHealth
    {
        return app(WebhookMetrics::class)->endpointHealth($this->id);
    }
}
