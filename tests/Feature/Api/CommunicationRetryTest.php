<?php

namespace Tests\Feature\Api;

use App\Enums\CommunicationLogEventEnum;
use App\Enums\CommunicationStatusEnum;
use App\Jobs\ProcessCommunicationJob;
use App\Models\Communication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CommunicationRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_retries_failed_communication(): void
    {
        Queue::fake();

        $communication = Communication::factory()->email()->create([
            'status' => CommunicationStatusEnum::Failed,
            'failure_reason' => 'erro anterior',
        ]);

        $this->postJson("/api/v1/communications/{$communication->id}/retry")
            ->assertAccepted()
            ->assertJsonPath('data.status', 'pending');

        $communication->refresh();
        $this->assertSame(CommunicationStatusEnum::Pending, $communication->status);
        $this->assertNull($communication->failure_reason);

        Queue::assertPushed(ProcessCommunicationJob::class);

        $this->assertDatabaseHas('communication_logs', [
            'communication_id' => $communication->id,
            'event' => CommunicationLogEventEnum::Retried->value,
        ]);
    }

    public function test_returns_409_when_status_is_not_failed(): void
    {
        $communication = Communication::factory()->email()->sent()->create();

        $this->postJson("/api/v1/communications/{$communication->id}/retry")
            ->assertStatus(409)
            ->assertJson([
                'error' => 'communication_not_retriable',
                'status' => 'sent',
            ]);
    }

    public function test_returns_friendly_404_for_unknown_id(): void
    {
        $this->postJson('/api/v1/communications/999/retry')
            ->assertNotFound()
            ->assertJsonPath('error', 'not_found');
    }
}
