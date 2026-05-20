<?php

namespace Tests\Feature\Api;

use App\Enums\CommunicationLogEventEnum;
use App\Models\Communication;
use App\Models\CommunicationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_communication_with_logs(): void
    {
        $communication = Communication::factory()->email()->sent()->create();
        CommunicationLog::query()->create([
            'communication_id' => $communication->id,
            'event' => CommunicationLogEventEnum::Sent,
            'message' => 'OK',
        ]);

        $response = $this->getJson("/api/v1/communications/{$communication->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $communication->id)
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonCount(1, 'data.logs')
            ->assertJsonPath('data.logs.0.event', CommunicationLogEventEnum::Sent->value);
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        $this->getJson('/api/v1/communications/999')
            ->assertNotFound()
            ->assertJson([
                'message' => 'Comunicação não encontrada.',
                'error' => 'not_found',
            ]);
    }

    public function test_unknown_api_route_returns_friendly_json_response(): void
    {
        $this->getJson('/api/v1/rota-inexistente')
            ->assertNotFound()
            ->assertJson([
                'message' => 'Rota não encontrada.',
                'error' => 'route_not_found',
            ]);
    }
}
