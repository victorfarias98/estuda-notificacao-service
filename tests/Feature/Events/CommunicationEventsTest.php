<?php

namespace Tests\Feature\Events;

use App\Events\CommunicationCreated;
use App\Events\CommunicationFailed;
use App\Events\CommunicationSent;
use App\Jobs\ProcessCommunicationJob;
use App\Models\Communication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommunicationEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_communication_created_on_store(): void
    {
        Event::fake([CommunicationCreated::class]);

        $this->postJson('/api/v1/communications', [
            'recipient' => 'event@example.com',
            'channel' => 'email',
            'subject' => 'Evento',
            'message' => 'corpo',
            'origin_system' => 'sistema-eventos',
        ])->assertAccepted();

        Event::assertDispatched(CommunicationCreated::class);
    }

    public function test_dispatches_sent_event_after_job_success(): void
    {
        Event::fake([CommunicationSent::class, CommunicationFailed::class]);
        Mail::fake();

        $communication = Communication::factory()->email()->create([
            'recipient' => 'sent-event@example.com',
            'subject' => 'Olá',
            'message' => 'corpo',
        ]);

        $this->app->call([new ProcessCommunicationJob($communication->id), 'handle']);

        Event::assertDispatched(CommunicationSent::class);
        Event::assertNotDispatched(CommunicationFailed::class);
    }
}
