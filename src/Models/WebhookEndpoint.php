<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
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
 * @property string|null $disabled_reason
 * @property Carbon|null $disabled_at
 * @property int|null $max_retries
 * @property string|null $retry_strategy
 * @property int|null $rate_limit_per_minute
 * @property array<int, string>|null $allowed_ips
 * @property string|null $previous_secret
 * @property Carbon|null $secret_rotated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
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
        'disabled_reason',
        'disabled_at',
        'max_retries',
        'retry_strategy',
        'rate_limit_per_minute',
        'allowed_ips',
        'previous_secret',
        'secret_rotated_at',
    ];

    protected $hidden = [
        'secret',
        'previous_secret',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'timeout_seconds' => 'integer',
        'disabled_at' => 'datetime',
        'allowed_ips' => 'array',
        'secret_rotated_at' => 'datetime',
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
     * @return HasMany<WebhookEndpointTag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(WebhookEndpointTag::class, 'endpoint_id');
    }

    /**
     * Attach a tag to this endpoint.
     */
    public function attachTag(string $tag): WebhookEndpointTag
    {
        return $this->tags()->firstOrCreate(['tag' => $tag], ['created_at' => Carbon::now()]);
    }

    /**
     * Detach a tag from this endpoint.
     */
    public function detachTag(string $tag): bool
    {
        return $this->tags()->where('tag', $tag)->delete() > 0;
    }

    /**
     * Determine if this endpoint has a given tag.
     */
    public function hasTag(string $tag): bool
    {
        return $this->tags()->where('tag', $tag)->exists();
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
     * Determine if this endpoint is currently disabled.
     */
    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    /**
     * Get the computed health status for this endpoint.
     */
    public function healthStatus(): EndpointHealth
    {
        return app(WebhookMetrics::class)->endpointHealth($this->id);
    }
}
