<?php

namespace Tests\Feature\Api;

use App\Models\Communication;
use App\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_communications_ordered_by_most_recent(): void
    {
        $older = Communication::factory()->email()->create();
        $newer = Communication::factory()->sms()->create();

        $response = $this->getJson('/api/v1/communications');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_filters_by_channel(): void
    {
        Communication::factory()->email()->count(2)->create();
        Communication::factory()->sms()->create();

        $this->getJson('/api/v1/communications?channel=email')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.channel', 'email');
    }

    public function test_filters_by_origin_system(): void
    {
        Communication::factory()->email()->create(['origin_system' => 'sistema-financeiro']);
        Communication::factory()->email()->create(['origin_system' => 'app-mobile']);

        $this->getJson('/api/v1/communications?origin_system=sistema-financeiro')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.origin_system', 'sistema-financeiro');
    }

    public function test_filters_by_recipient_partial_match(): void
    {
        Communication::factory()->email()->create(['recipient' => 'victor@example.com']);
        Communication::factory()->email()->create(['recipient' => 'alice@example.com']);

        $this->getJson('/api/v1/communications?recipient=victor')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.recipient', 'victor@example.com');
    }

    public function test_filters_by_template_slug(): void
    {
        $template = NotificationTemplate::factory()->sms()->create(['slug' => 'codigo-otp']);

        Communication::factory()->sms()->create(['notification_template_id' => $template->id]);
        Communication::factory()->sms()->create();

        $this->getJson('/api/v1/communications?template_slug=codigo-otp')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.template.slug', 'codigo-otp');
    }

    public function test_filters_by_template_id(): void
    {
        $template = NotificationTemplate::factory()->push()->create();

        Communication::factory()->push()->create(['notification_template_id' => $template->id]);
        Communication::factory()->push()->create();

        $this->getJson("/api/v1/communications?template_id={$template->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.template.id', $template->id);
    }

    public function test_filters_by_status(): void
    {
        Communication::factory()->email()->sent()->create();
        Communication::factory()->email()->failed()->create();

        $this->getJson('/api/v1/communications?status=sent')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'sent');
    }

    public function test_combines_multiple_filters(): void
    {
        $template = NotificationTemplate::factory()->email()->create(['slug' => 'boas-vindas']);

        $match = Communication::factory()->email()->sent()->create([
            'origin_system' => 'sistema-financeiro',
            'recipient' => 'victor@example.com',
            'notification_template_id' => $template->id,
        ]);

        Communication::factory()->email()->sent()->create([
            'origin_system' => 'app-mobile',
            'recipient' => 'victor@example.com',
            'notification_template_id' => $template->id,
        ]);
        Communication::factory()->sms()->sent()->create();

        $this->getJson('/api/v1/communications?channel=email&origin_system=sistema-financeiro&recipient=victor&template_slug=boas-vindas&status=sent')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_rejects_invalid_channel_filter(): void
    {
        $this->getJson('/api/v1/communications?channel=fax')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('channel');
    }
}
