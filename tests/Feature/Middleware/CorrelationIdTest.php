<?php

namespace Tests\Feature\Middleware;

use App\Models\Communication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrelationIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_propagates_correlation_id_header_in_response(): void
    {
        $response = $this->postJson('/api/v1/communications', [
            'recipient' => 'corr@example.com',
            'channel' => 'email',
            'subject' => 'Corr',
            'message' => 'corpo',
            'origin_system' => 'sistema-corr',
        ], [
            'X-Request-Id' => 'req-uuid-12345',
        ])->assertAccepted();

        $response->assertHeader('X-Request-Id', 'req-uuid-12345');

        $communication = Communication::query()->find($response->json('data.id'));
        $this->assertSame('req-uuid-12345', $communication?->correlation_id);
    }

    public function test_generates_correlation_id_when_header_missing(): void
    {
        $response = $this->postJson('/api/v1/communications', [
            'recipient' => 'auto@example.com',
            'channel' => 'email',
            'subject' => 'Auto',
            'message' => 'corpo',
            'origin_system' => 'sistema-corr',
        ])->assertAccepted();

        $header = $response->headers->get('X-Request-Id');
        $this->assertNotEmpty($header);

        $communication = Communication::query()->find($response->json('data.id'));
        $this->assertSame($header, $communication?->correlation_id);
    }
}
