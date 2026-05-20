<?php

namespace Tests\Unit\Services;

use App\Enums\CommunicationLogEventEnum;
use App\Enums\CommunicationStatusEnum;
use App\Jobs\ProcessCommunicationJob;
use App\Models\NotificationTemplate;
use App\Services\CommunicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CommunicationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_dispatch_persists_and_queues_job(): void
    {
        Queue::fake();

        $template = NotificationTemplate::factory()->sms()->create();
        $service = $this->app->make(CommunicationService::class);

        $communication = $service->createAndDispatch([
            'recipient' => '+5511999999999',
            'channel' => 'sms',
            'message' => 'fallback',
            'origin_system' => 'sistema',
            'variables' => ['codigo' => '123'],
        ], $template);

        $this->assertSame(CommunicationStatusEnum::Pending, $communication->status);
        $this->assertSame($template->id, $communication->notification_template_id);
        $this->assertNotNull($communication->queued_at);

        Queue::assertPushed(ProcessCommunicationJob::class, fn (ProcessCommunicationJob $job): bool => $job->communicationId === $communication->id);

        $this->assertDatabaseHas('communication_logs', [
            'communication_id' => $communication->id,
            'event' => CommunicationLogEventEnum::Received->value,
        ]);
        $this->assertDatabaseHas('communication_logs', [
            'communication_id' => $communication->id,
            'event' => CommunicationLogEventEnum::Queued->value,
        ]);
    }
}
