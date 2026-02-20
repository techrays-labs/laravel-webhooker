<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker\Tests\Unit;

use TechRaysLabs\Webhooker\Models\WebhookAttempt;
use TechRaysLabs\Webhooker\Tests\TestCase;

class WebhookAttemptModelTest extends TestCase
{
    public function test_is_successful_for_2xx_status(): void
    {
        $attempt = new WebhookAttempt(['response_status' => 200]);
        $this->assertTrue($attempt->isSuccessful());

        $attempt->response_status = 201;
        $this->assertTrue($attempt->isSuccessful());

        $attempt->response_status = 299;
        $this->assertTrue($attempt->isSuccessful());
    }

    public function test_is_not_successful_for_non_2xx_status(): void
    {
        $attempt = new WebhookAttempt(['response_status' => 400]);
        $this->assertFalse($attempt->isSuccessful());

        $attempt->response_status = 500;
        $this->assertFalse($attempt->isSuccessful());

        $attempt->response_status = 301;
        $this->assertFalse($attempt->isSuccessful());
    }

    public function test_is_not_successful_for_null_status(): void
    {
        $attempt = new WebhookAttempt(['response_status' => null]);
        $this->assertFalse($attempt->isSuccessful());
    }

    public function test_request_headers_are_cast_to_array(): void
    {
        $attempt = new WebhookAttempt([
            'request_headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->assertIsArray($attempt->request_headers);
    }
}
