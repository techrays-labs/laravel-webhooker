<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $class
 * @property array|null $config
 * @property bool $is_enabled
 * @property int $priority
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class WebhookPlugin extends Model
{
    protected $table = 'webhook_plugins';

    protected $fillable = [
        'name',
        'class',
        'config',
        'is_enabled',
        'priority',
    ];

    protected $casts = [
        'config' => 'array',
        'is_enabled' => 'boolean',
        'priority' => 'integer',
    ];

    public function isEnabled(): bool
    {
        return $this->is_enabled;
    }

    public function getInstance(): ?object
    {
        if (!class_exists($this->class)) {
            return null;
        }

        $config = $this->config ?? [];

        return app($this->class, ['config' => $config]);
    }

    public static function getEnabledPlugins(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_enabled', true)
            ->orderBy('priority', 'desc')
            ->get();
    }

    public static function findByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }
}
