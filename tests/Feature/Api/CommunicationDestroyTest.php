<?php

namespace Tests\Feature\Api;

use App\Enums\CommunicationLogEventEnum;
use App\Enums\CommunicationStatusEnum;
use App\Models\Communication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancels_pending_communication_with_soft_delete(): void
    {
        $communication = Communication::factory()->email()->create([
            'status' => CommunicationStatusEnum::Pending,
        ]);

        $this->deleteJson("/api/v1/communications/{$communication->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('communications', ['id' => $communication->id]);
        $this->assertDatabaseHas('communications', [
            'id' => $communication->id,
            'status' => CommunicationStatusEnum::Cancelled->value,
        ]);
        $this->assertDatabaseHas('communication_logs', [
            'communication_id' => $communication->id,
            'event' => CommunicationLogEventEnum::Cancelled->value,
        ]);
    }

    public function test_cancelled_communication_hidden_from_default_list(): void
    {
        $communication = Communication::factory()->email()->create([
            'status' => CommunicationStatusEnum::Pending,
        ]);

        $this->deleteJson("/api/v1/communications/{$communication->id}")->assertNoContent();

        $this->getJson('/api/v1/communications')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/communications?include_cancelled=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $communication->id)
            ->assertJsonPath('data.0.status', 'cancelled');
    }

    public function test_returns_409_when_communication_already_sent(): void
    {
        $communication = Communication::factory()->email()->sent()->create();

        $this->deleteJson("/api/v1/communications/{$communication->id}")
            ->assertStatus(409)
            ->assertJson([
                'error' => 'communication_not_editable',
                'status' => 'sent',
            ]);

        $this->assertDatabaseHas('communications', ['id' => $communication->id]);
    }

    public function test_returns_friendly_404_when_destroy_id_unknown(): void
    {
        $this->deleteJson('/api/v1/communications/999')
            ->assertNotFound()
            ->assertJson([
                'message' => 'Comunicação não encontrada.',
                'error' => 'not_found',
            ]);
    }
}
