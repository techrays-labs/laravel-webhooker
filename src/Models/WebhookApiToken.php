<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

/**
 * @property int $id
 * @property string $name
 * @property string $token_hash
 * @property array|null $abilities
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class WebhookApiToken extends Model
{
    protected $table = 'webhook_api_tokens';

    protected $fillable = [
        'name',
        'token_hash',
        'abilities',
        'expires_at',
        'last_used_at',
        'is_active',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'abilities' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public static function createToken(string $name, ?array $abilities = null, ?\DateTimeInterface $expiresAt = null): array
    {
        $token = 'whk_'.Str::random(60);
        
        $record = static::create([
            'name' => $name,
            'token_hash' => Hash::make($token),
            'abilities' => $abilities ?? ['*'],
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);

        return [
            'token' => $token,
            'id' => $record->id,
            'name' => $record->name,
            'abilities' => $record->abilities,
            'expires_at' => $record->expires_at,
        ];
    }

    public function isTokenValid(string $token): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return Hash::check($token, $this->token_hash);
    }

    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function can(string $ability): bool
    {
        $abilities = $this->abilities ?? [];

        if (in_array('*', $abilities)) {
            return true;
        }

        return in_array($ability, $abilities);
    }
}
