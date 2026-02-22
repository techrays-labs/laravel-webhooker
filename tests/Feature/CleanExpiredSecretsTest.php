<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use Illuminate\Support\Carbon;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Tests\TestCase;

class CleanExpiredSecretsTest extends TestCase
{
    public function test_cleanup_removes_expired_previous_secrets(): void
    {
        config(['webhooks.secret_rotation.grace_period_hours' => 24]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'new-secret',
            'previous_secret' => 'old-secret',
            'secret_rotated_at' => Carbon::now()->subHours(48),
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $this->artisan('webhook:secret:cleanup')
            ->expectsOutputToContain('Cleaned up 1 expired previous secret(s)')
            ->assertSuccessful();

        $endpoint->refresh();
        $this->assertNull($endpoint->previous_secret);
        $this->assertNull($endpoint->secret_rotated_at);
    }

    public function test_cleanup_preserves_secrets_within_grace_period(): void
    {
        config(['webhooks.secret_rotation.grace_period_hours' => 24]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'new-secret',
            'previous_secret' => 'old-secret',
            'secret_rotated_at' => Carbon::now()->subHours(12),
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $this->artisan('webhook:secret:cleanup')
            ->expectsOutputToContain('Cleaned up 0 expired previous secret(s)')
            ->assertSuccessful();

        $endpoint->refresh();
        $this->assertEquals('old-secret', $endpoint->previous_secret);
        $this->assertNotNull($endpoint->secret_rotated_at);
    }

    public function test_cleanup_ignores_endpoints_without_previous_secret(): void
    {
        WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $this->artisan('webhook:secret:cleanup')
            ->expectsOutputToContain('Cleaned up 0 expired previous secret(s)')
            ->assertSuccessful();
    }

    public function test_cleanup_handles_multiple_endpoints(): void
    {
        config(['webhooks.secret_rotation.grace_period_hours' => 24]);

        // Expired
        WebhookEndpoint::create([
            'name' => 'Expired 1',
            'url' => 'https://example.com/hook1',
            'direction' => 'outbound',
            'secret' => 'new-1',
            'previous_secret' => 'old-1',
            'secret_rotated_at' => Carbon::now()->subHours(48),
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        // Still in grace period
        WebhookEndpoint::create([
            'name' => 'Fresh',
            'url' => 'https://example.com/hook2',
            'direction' => 'outbound',
            'secret' => 'new-2',
            'previous_secret' => 'old-2',
            'secret_rotated_at' => Carbon::now()->subHours(6),
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        // Expired
        WebhookEndpoint::create([
            'name' => 'Expired 2',
            'url' => 'https://example.com/hook3',
            'direction' => 'outbound',
            'secret' => 'new-3',
            'previous_secret' => 'old-3',
            'secret_rotated_at' => Carbon::now()->subHours(72),
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $this->artisan('webhook:secret:cleanup')
            ->expectsOutputToContain('Cleaned up 2 expired previous secret(s)')
            ->assertSuccessful();
    }
}
