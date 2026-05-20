<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_limits_requests_by_origin_system(): void
    {
        config(['notifications.rate_limit_per_minute' => 2]);

        $payload = [
            'recipient' => 'rate@example.com',
            'channel' => 'email',
            'subject' => 'Limite',
            'message' => 'corpo',
            'origin_system' => 'sistema-com-limite',
        ];

        $this->postJson('/api/v1/communications', $payload)->assertAccepted();
        $this->postJson('/api/v1/communications', $payload)->assertAccepted();
        $this->postJson('/api/v1/communications', $payload)->assertStatus(429);
    }
}
