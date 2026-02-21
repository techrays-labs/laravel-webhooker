<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $endpoint_id
 * @property string $tag
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class WebhookEndpointTag extends Model
{
    protected $table = 'webhook_endpoint_tags';

    public $timestamps = false;

    protected $fillable = [
        'endpoint_id',
        'tag',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id');
    }
}
