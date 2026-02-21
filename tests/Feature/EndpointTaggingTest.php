<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use TechraysLabs\Webhooker\Jobs\DispatchWebhookJob;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Tests\TestCase;
use TechraysLabs\Webhooker\Webhooker;

class EndpointTaggingTest extends TestCase
{
    private WebhookEndpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = WebhookEndpoint::create([
            'name' => 'Tagged Endpoint',
            'url' => 'https://example.com/webhook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);
    }

    public function test_attach_tag_to_endpoint(): void
    {
        $tag = $this->endpoint->attachTag('payments');

        $this->assertEquals('payments', $tag->tag);
        $this->assertEquals($this->endpoint->id, $tag->endpoint_id);
        $this->assertDatabaseHas('webhook_endpoint_tags', [
            'endpoint_id' => $this->endpoint->id,
            'tag' => 'payments',
        ]);
    }

    public function test_attach_duplicate_tag_returns_existing(): void
    {
        $tag1 = $this->endpoint->attachTag('payments');
        $tag2 = $this->endpoint->attachTag('payments');

        $this->assertEquals($tag1->id, $tag2->id);
        $this->assertCount(1, $this->endpoint->tags);
    }

    public function test_detach_tag_from_endpoint(): void
    {
        $this->endpoint->attachTag('payments');
        $result = $this->endpoint->detachTag('payments');

        $this->assertTrue($result);
        $this->assertDatabaseMissing('webhook_endpoint_tags', [
            'endpoint_id' => $this->endpoint->id,
            'tag' => 'payments',
        ]);
    }

    public function test_detach_nonexistent_tag_returns_false(): void
    {
        $this->assertFalse($this->endpoint->detachTag('nonexistent'));
    }

    public function test_has_tag(): void
    {
        $this->assertFalse($this->endpoint->hasTag('payments'));

        $this->endpoint->attachTag('payments');

        $this->assertTrue($this->endpoint->hasTag('payments'));
    }

    public function test_tags_relationship(): void
    {
        $this->endpoint->attachTag('payments');
        $this->endpoint->attachTag('notifications');

        $tags = $this->endpoint->tags()->get();

        $this->assertCount(2, $tags);
        $this->assertTrue($tags->pluck('tag')->contains('payments'));
        $this->assertTrue($tags->pluck('tag')->contains('notifications'));
    }

    public function test_get_endpoints_by_tag(): void
    {
        $endpoint2 = WebhookEndpoint::create([
            'name' => 'Second Endpoint',
            'url' => 'https://example2.com/webhook',
            'direction' => 'outbound',
            'secret' => 'test-secret-2',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $this->endpoint->attachTag('payments');
        $endpoint2->attachTag('payments');
        $endpoint2->attachTag('orders');

        $webhooker = app(Webhooker::class);
        $repository = app(\TechraysLabs\Webhooker\Contracts\WebhookRepository::class);

        $paymentEndpoints = $repository->getEndpointsByTag('payments');
        $this->assertCount(2, $paymentEndpoints);

        $orderEndpoints = $repository->getEndpointsByTag('orders');
        $this->assertCount(1, $orderEndpoints);
    }

    public function test_dispatch_to_tag(): void
    {
        Bus::fake();

        $endpoint2 = WebhookEndpoint::create([
            'name' => 'Second Endpoint',
            'url' => 'https://example2.com/webhook',
            'direction' => 'outbound',
            'secret' => 'test-secret-2',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $this->endpoint->attachTag('payments');
        $endpoint2->attachTag('payments');

        $webhooker = app(Webhooker::class);
        $events = $webhooker->dispatchToTag('payments', 'order.created', ['amount' => 100]);

        $this->assertCount(2, $events);
        Bus::assertDispatched(DispatchWebhookJob::class, 2);
    }

    public function test_endpoint_list_command_with_tag_filter(): void
    {
        $this->endpoint->attachTag('payments');

        WebhookEndpoint::create([
            'name' => 'Untagged',
            'url' => 'https://example2.com/webhook',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $this->artisan('webhook:endpoint:list', ['--tag' => 'payments'])
            ->expectsTable(
                ['Token', 'Name', 'URL', 'Direction', 'Active', 'Timeout', 'Tags'],
                [[$this->endpoint->route_token, 'Tagged Endpoint', 'https://example.com/webhook', 'outbound', 'Yes', '30s', 'payments']],
            )
            ->assertExitCode(0);
    }
}
