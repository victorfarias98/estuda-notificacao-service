<?php

namespace Tests\Feature\Api;

use App\Models\IdempotencyKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_same_response_for_duplicate_idempotency_key(): void
    {
        $payload = [
            'recipient' => 'idempotent@example.com',
            'channel' => 'email',
            'subject' => 'Teste',
            'message' => 'corpo',
            'origin_system' => 'sistema-idempotente',
        ];

        $first = $this->postJson('/api/v1/communications', $payload, [
            'Idempotency-Key' => 'key-abc-123',
        ])->assertAccepted();

        $second = $this->postJson('/api/v1/communications', $payload, [
            'Idempotency-Key' => 'key-abc-123',
        ])->assertAccepted();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, IdempotencyKey::query()->where('key', 'key-abc-123')->count());
    }

    public function test_rejects_conflicting_body_for_same_idempotency_key(): void
    {
        $this->postJson('/api/v1/communications', [
            'recipient' => 'a@example.com',
            'channel' => 'email',
            'subject' => 'A',
            'message' => 'corpo',
            'origin_system' => 'sistema-idempotente',
        ], ['Idempotency-Key' => 'key-conflict'])->assertAccepted();

        $this->postJson('/api/v1/communications', [
            'recipient' => 'b@example.com',
            'channel' => 'email',
            'subject' => 'B',
            'message' => 'outro corpo',
            'origin_system' => 'sistema-idempotente',
        ], ['Idempotency-Key' => 'key-conflict'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'idempotency_conflict');
    }
}
