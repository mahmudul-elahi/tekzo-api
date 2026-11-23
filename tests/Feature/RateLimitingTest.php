<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    public function test_auth_rate_limiter_throttles_excessive_login_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'secret123',
            ]);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(429);
    }

    public function test_invoice_tracking_rate_limiter_throttles_excessive_requests(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/order-tracking', [
                'order_id' => 'ORD-12345',
            ]);
        }

        $response = $this->postJson('/api/v1/order-tracking', [
            'order_id' => 'ORD-12345',
        ]);

        $response->assertStatus(429);
    }
}
