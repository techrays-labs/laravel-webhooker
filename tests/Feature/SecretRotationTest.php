<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use TechraysLabs\Webhooker\Contracts\SignatureGenerator;
use TechraysLabs\Webhooker\Events\EndpointSecretRotated;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Tests\TestCase;
use TechraysLabs\Webhooker\Webhooker;

class SecretRotationTest extends TestCase
{
    public function test_rotate_secret_generates_new_secret(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'original-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $webhooker = app(Webhooker::class);
        $newSecret = $webhooker->rotateSecret($endpoint->id);

        $endpoint->refresh();
        $this->assertEquals($newSecret, $endpoint->secret);
        $this->assertEquals('original-secret', $endpoint->previous_secret);
        $this->assertNotNull($endpoint->secret_rotated_at);
    }

    public function test_rotate_secret_fires_event(): void
    {
        Event::fake([EndpointSecretRotated::class]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'original-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        app(Webhooker::class)->rotateSecret($endpoint->id);

        Event::assertDispatched(EndpointSecretRotated::class);
    }

    public function test_rotate_secret_throws_for_invalid_endpoint(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(Webhooker::class)->rotateSecret(99999);
    }

    public function test_secret_rotate_command(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'original-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $this->artisan('webhook:secret:rotate', ['endpoint_id' => $endpoint->id])
            ->assertSuccessful();

        $endpoint->refresh();
        $this->assertNotEquals('original-secret', $endpoint->secret);
        $this->assertEquals('original-secret', $endpoint->previous_secret);
    }

    public function test_secret_rotate_command_fails_for_invalid_endpoint(): void
    {
        $this->artisan('webhook:secret:rotate', ['endpoint_id' => 99999])
            ->assertFailed();
    }

    public function test_inbound_accepts_previous_secret_during_grace_period(): void
    {
        $signer = app(SignatureGenerator::class);
        $originalSecret = 'original-secret-key';

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test Inbound',
            'url' => 'https://example.com/inbound',
            'direction' => 'inbound',
            'secret' => 'new-secret-key',
            'previous_secret' => $originalSecret,
            'secret_rotated_at' => Carbon::now()->subHours(1),
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        config(['webhooks.secret_rotation.grace_period_hours' => 24]);

        $payload = '{"event":"test"}';
        $signature = $signer->generate($payload, $originalSecret);
        $signatureHeader = config('webhooks.signature_header', 'X-Webhook-Signature');

        $response = $this->postJson(
            "/api/webhooks/inbound/{$endpoint->route_token}",
            json_decode($payload, true),
            [$signatureHeader => $signature]
        );

        // Should accept old secret during grace period (not 401)
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function test_inbound_rejects_previous_secret_after_grace_period(): void
    {
        $signer = app(SignatureGenerator::class);
        $originalSecret = 'original-secret-key';

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test Inbound',
            'url' => 'https://example.com/inbound',
            'direction' => 'inbound',
            'secret' => 'new-secret-key',
            'previous_secret' => $originalSecret,
            'secret_rotated_at' => Carbon::now()->subHours(48),
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        config(['webhooks.secret_rotation.grace_period_hours' => 24]);

        $payload = '{"event":"test"}';
        $signature = $signer->generate($payload, $originalSecret);
        $signatureHeader = config('webhooks.signature_header', 'X-Webhook-Signature');

        $response = $this->postJson(
            "/api/webhooks/inbound/{$endpoint->route_token}",
            json_decode($payload, true),
            [$signatureHeader => $signature]
        );

        $response->assertStatus(401);
    }
}
