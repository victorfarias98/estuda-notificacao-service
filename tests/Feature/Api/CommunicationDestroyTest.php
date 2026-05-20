<?php

namespace Tests\Feature\Api;

use App\Enums\CommunicationStatusEnum;
use App\Models\Communication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_pending_communication(): void
    {
        $communication = Communication::factory()->email()->create([
            'status' => CommunicationStatusEnum::Pending,
        ]);

        $this->deleteJson("/api/communications/{$communication->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('communications', ['id' => $communication->id]);
    }

    public function test_returns_409_when_communication_already_sent(): void
    {
        $communication = Communication::factory()->email()->sent()->create();

        $this->deleteJson("/api/communications/{$communication->id}")
            ->assertStatus(409)
            ->assertJson([
                'error' => 'communication_not_editable',
                'status' => 'sent',
            ]);

        $this->assertDatabaseHas('communications', ['id' => $communication->id]);
    }

    public function test_returns_friendly_404_when_destroy_id_unknown(): void
    {
        $this->deleteJson('/api/communications/999')
            ->assertNotFound()
            ->assertJson([
                'message' => 'Comunicação não encontrada.',
                'error' => 'not_found',
            ]);
    }
}
