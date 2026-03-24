<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $event_name
 * @property array $schema
 * @property string|null $description
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class WebhookEventSchema extends Model
{
    protected $table = 'webhook_event_schemas';

    protected $fillable = [
        'event_name',
        'schema',
        'description',
        'is_active',
    ];

    protected $casts = [
        'schema' => 'array',
        'is_active' => 'boolean',
    ];

    public static function findByEventName(string $eventName): ?self
    {
        return static::where('event_name', $eventName)
            ->where('is_active', true)
            ->first();
    }

    public function validatePayload(array $payload): bool
    {
        $validator = app(\Illuminate\Contracts\Validation\Factory::class)->make(
            $payload,
            $this->schema
        );

        return (bool) $validator->passes();
    }
}
