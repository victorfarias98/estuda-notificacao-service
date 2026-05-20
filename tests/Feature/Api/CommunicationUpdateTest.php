<?php

namespace Tests\Feature\Api;

use App\Enums\CommunicationLogEventEnum;
use App\Models\Communication;
use App\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_pending_communication_fields(): void
    {
        $communication = Communication::factory()->email()->create([
            'subject' => 'antigo',
            'message' => 'mensagem antiga',
            'recipient' => 'antigo@example.com',
        ]);

        $response = $this->patchJson("/api/communications/{$communication->id}", [
            'subject' => 'novo assunto',
            'message' => 'novo corpo',
            'recipient' => 'novo@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.subject', 'novo assunto')
            ->assertJsonPath('data.message', 'novo corpo')
            ->assertJsonPath('data.recipient', 'novo@example.com')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('communications', [
            'id' => $communication->id,
            'subject' => 'novo assunto',
            'message' => 'novo corpo',
            'recipient' => 'novo@example.com',
        ]);

        $this->assertDatabaseHas('communication_logs', [
            'communication_id' => $communication->id,
            'event' => CommunicationLogEventEnum::Received->value,
            'message' => 'Comunicação atualizada antes do envio.',
        ]);
    }

    public function test_attaches_template_when_updating(): void
    {
        $template = NotificationTemplate::factory()->sms()->create(['slug' => 'codigo-otp']);
        $communication = Communication::factory()->sms()->create([
            'notification_template_id' => null,
        ]);

        $response = $this->patchJson("/api/communications/{$communication->id}", [
            'template_slug' => 'codigo-otp',
            'variables' => ['codigo' => '987654'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.template.slug', 'codigo-otp')
            ->assertJsonPath('data.variables.codigo', '987654');

        $this->assertDatabaseHas('communications', [
            'id' => $communication->id,
            'notification_template_id' => $template->id,
        ]);
    }

    public function test_rejects_template_from_a_different_channel(): void
    {
        $template = NotificationTemplate::factory()->email()->create();
        $communication = Communication::factory()->sms()->create();

        $this->patchJson("/api/communications/{$communication->id}", [
            'template_slug' => $template->slug,
        ])->assertUnprocessable()->assertJsonValidationErrors('template_slug');
    }

    public function test_returns_409_when_status_is_not_pending(): void
    {
        $communication = Communication::factory()->email()->sent()->create();

        $this->patchJson("/api/communications/{$communication->id}", [
            'subject' => 'qualquer',
        ])
            ->assertStatus(409)
            ->assertJson([
                'error' => 'communication_not_editable',
                'status' => 'sent',
            ])
            ->assertJsonStructure(['message']);
    }

    public function test_validates_email_format_on_update_for_email_channel(): void
    {
        $communication = Communication::factory()->email()->create();

        $this->patchJson("/api/communications/{$communication->id}", [
            'recipient' => 'nao-eh-email',
        ])->assertUnprocessable()->assertJsonValidationErrors('recipient');
    }

    public function test_returns_friendly_404_when_id_unknown(): void
    {
        $this->patchJson('/api/communications/999', ['subject' => 'x'])
            ->assertNotFound()
            ->assertJson([
                'message' => 'Comunicação não encontrada.',
                'error' => 'not_found',
            ]);
    }
}
