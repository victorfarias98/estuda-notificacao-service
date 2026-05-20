<?php

namespace Tests\Feature\Api;

use App\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTemplateCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_templates_filtered_by_channel(): void
    {
        NotificationTemplate::factory()->email()->count(2)->create();
        NotificationTemplate::factory()->sms()->create();

        $response = $this->getJson('/api/v1/notification-templates?channel=email');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_creates_email_template(): void
    {
        $payload = [
            'name' => 'Boas-vindas',
            'slug' => 'boas-vindas',
            'channel' => 'email',
            'subject' => 'Bem-vindo {{nome}}',
            'body' => 'Olá {{nome}}',
            'description' => 'Boas-vindas para novos usuários',
        ];

        $response = $this->postJson('/api/v1/notification-templates', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'boas-vindas')
            ->assertJsonPath('data.channel', 'email');

        $this->assertDatabaseHas('notification_templates', [
            'slug' => 'boas-vindas',
            'channel' => 'email',
        ]);
    }

    public function test_creates_sms_template_without_subject(): void
    {
        $response = $this->postJson('/api/v1/notification-templates', [
            'name' => 'OTP',
            'slug' => 'otp',
            'channel' => 'sms',
            'body' => 'Codigo {{codigo}}',
        ]);

        $response->assertCreated();
    }

    public function test_requires_subject_for_email_template(): void
    {
        $response = $this->postJson('/api/v1/notification-templates', [
            'name' => 'Boas-vindas',
            'slug' => 'boas-vindas',
            'channel' => 'email',
            'body' => 'corpo',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('subject');
    }

    public function test_disallows_duplicate_slug_within_same_channel(): void
    {
        NotificationTemplate::factory()->sms()->create(['slug' => 'otp']);

        $response = $this->postJson('/api/v1/notification-templates', [
            'name' => 'Outro OTP',
            'slug' => 'otp',
            'channel' => 'sms',
            'body' => 'corpo {{codigo}}',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('slug');
    }

    public function test_allows_same_slug_across_different_channels(): void
    {
        NotificationTemplate::factory()->sms()->create(['slug' => 'aviso']);

        $response = $this->postJson('/api/v1/notification-templates', [
            'name' => 'Aviso push',
            'slug' => 'aviso',
            'channel' => 'push',
            'body' => 'Alerta',
        ]);

        $response->assertCreated();
    }

    public function test_shows_template(): void
    {
        $template = NotificationTemplate::factory()->push()->create();

        $this->getJson("/api/v1/notification-templates/{$template->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $template->id);
    }

    public function test_show_returns_friendly_response_for_unknown_template(): void
    {
        $this->getJson('/api/v1/notification-templates/999')
            ->assertNotFound()
            ->assertJson([
                'message' => 'Template de notificação não encontrado.',
                'error' => 'not_found',
            ]);
    }

    public function test_updates_template(): void
    {
        $template = NotificationTemplate::factory()->push()->create();

        $response = $this->patchJson("/api/v1/notification-templates/{$template->id}", [
            'name' => 'Atualizado',
            'body' => 'novo corpo',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Atualizado');
        $this->assertDatabaseHas('notification_templates', [
            'id' => $template->id,
            'name' => 'Atualizado',
            'body' => 'novo corpo',
        ]);
    }

    public function test_deletes_template(): void
    {
        $template = NotificationTemplate::factory()->push()->create();

        $this->deleteJson("/api/v1/notification-templates/{$template->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('notification_templates', ['id' => $template->id]);
    }
}
