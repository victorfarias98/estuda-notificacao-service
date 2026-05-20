<?php

namespace Tests\Feature\Api;

use App\Enums\CommunicationLogEventEnum;
use App\Enums\CommunicationStatusEnum;
use App\Jobs\ProcessCommunicationJob;
use App\Models\Communication;
use App\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CommunicationStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_email_communication_and_dispatches_job(): void
    {
        Queue::fake();

        $payload = [
            'recipient' => 'usuario@email.com',
            'channel' => 'email',
            'subject' => 'Bem-vindo',
            'message' => 'Sua conta foi criada com sucesso.',
            'origin_system' => 'sistema-financeiro',
        ];

        $response = $this->postJson('/api/communications', $payload);

        $response->assertAccepted()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonPath('data.origin_system', 'sistema-financeiro');

        $this->assertDatabaseHas('communications', [
            'recipient' => 'usuario@email.com',
            'channel' => 'email',
            'status' => CommunicationStatusEnum::Pending->value,
        ]);

        $communication = Communication::query()->first();
        $this->assertNotNull($communication);
        $this->assertNotNull($communication->queued_at);

        $this->assertDatabaseHas('communication_logs', [
            'communication_id' => $communication->id,
            'event' => CommunicationLogEventEnum::Received->value,
        ]);
        $this->assertDatabaseHas('communication_logs', [
            'communication_id' => $communication->id,
            'event' => CommunicationLogEventEnum::Queued->value,
        ]);

        Queue::assertPushed(ProcessCommunicationJob::class, fn (ProcessCommunicationJob $job): bool => $job->communicationId === $communication->id);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/api/communications', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient', 'channel', 'origin_system']);
    }

    public function test_rejects_invalid_channel(): void
    {
        $response = $this->postJson('/api/communications', [
            'recipient' => 'usuario@email.com',
            'channel' => 'fax',
            'message' => 'oi',
            'origin_system' => 'sistema',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('channel');
    }

    public function test_requires_subject_when_channel_is_email_without_template(): void
    {
        $response = $this->postJson('/api/communications', [
            'recipient' => 'usuario@email.com',
            'channel' => 'email',
            'message' => 'corpo',
            'origin_system' => 'sistema',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('subject');
    }

    public function test_requires_valid_email_recipient_for_email_channel(): void
    {
        $response = $this->postJson('/api/communications', [
            'recipient' => 'nao-eh-email',
            'channel' => 'email',
            'subject' => 'Assunto',
            'message' => 'corpo',
            'origin_system' => 'sistema',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('recipient');
    }

    public function test_requires_message_when_no_template_is_provided(): void
    {
        $response = $this->postJson('/api/communications', [
            'recipient' => '+5511999999999',
            'channel' => 'sms',
            'origin_system' => 'sistema',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('message');
    }

    public function test_accepts_template_reference_and_persists_variables(): void
    {
        Queue::fake();

        $template = NotificationTemplate::factory()->sms()->create([
            'slug' => 'codigo-otp',
        ]);

        $response = $this->postJson('/api/communications', [
            'recipient' => '+5511999999999',
            'channel' => 'sms',
            'origin_system' => 'sistema-financeiro',
            'template_slug' => $template->slug,
            'variables' => ['codigo' => '123456'],
        ]);

        $response->assertAccepted();

        $this->assertDatabaseHas('communications', [
            'recipient' => '+5511999999999',
            'channel' => 'sms',
            'notification_template_id' => $template->id,
        ]);
    }

    public function test_rejects_template_from_other_channel(): void
    {
        $template = NotificationTemplate::factory()->email()->create();

        $response = $this->postJson('/api/communications', [
            'recipient' => '+5511999999999',
            'channel' => 'sms',
            'message' => 'fallback',
            'origin_system' => 'sistema',
            'template_slug' => $template->slug,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('template_slug');
    }

    public function test_rejects_inactive_template(): void
    {
        $template = NotificationTemplate::factory()->sms()->inactive()->create();

        $response = $this->postJson('/api/communications', [
            'recipient' => '+5511999999999',
            'channel' => 'sms',
            'origin_system' => 'sistema',
            'template_slug' => $template->slug,
            'variables' => ['codigo' => '123'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('template_slug');
    }
}
