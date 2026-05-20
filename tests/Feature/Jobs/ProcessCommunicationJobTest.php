<?php

namespace Tests\Feature\Jobs;

use App\Channels\ChannelSenderFactory;
use App\Contracts\ChannelSenderInterface;
use App\DTOs\OutboundMessage;
use App\Enums\CommunicationChannelEnum;
use App\Enums\CommunicationLogEventEnum;
use App\Enums\CommunicationStatusEnum;
use App\Jobs\ProcessCommunicationJob;
use App\Mail\CommunicationMail;
use App\Models\Communication;
use App\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class ProcessCommunicationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_processes_email_communication_and_sends_mail(): void
    {
        Mail::fake();

        $communication = Communication::factory()->email()->create([
            'recipient' => 'foo@bar.com',
            'subject' => 'Olá',
            'message' => 'mensagem direta',
        ]);

        $this->app->call([new ProcessCommunicationJob($communication->id), 'handle']);

        $communication->refresh();
        $this->assertSame(CommunicationStatusEnum::Sent, $communication->status);
        $this->assertNotNull($communication->sent_at);
        $this->assertSame(1, $communication->attempts);

        Mail::assertSent(CommunicationMail::class, fn (CommunicationMail $mail): bool => $mail->hasTo('foo@bar.com')
                && $mail->subjectLine === 'Olá'
                && $mail->bodyContent === 'mensagem direta');

        $this->assertDatabaseHas('communication_logs', [
            'communication_id' => $communication->id,
            'event' => CommunicationLogEventEnum::Sent->value,
        ]);
    }

    public function test_renders_template_when_provided(): void
    {
        Mail::fake();

        $template = NotificationTemplate::factory()->email()->create([
            'slug' => 'boas-vindas',
            'subject' => 'Bem-vindo {{nome}}',
            'body' => 'Olá {{nome}}, bem-vindo ao {{empresa}}.',
        ]);

        $communication = Communication::factory()->email()->create([
            'recipient' => 'foo@bar.com',
            'subject' => null,
            'message' => null,
            'notification_template_id' => $template->id,
            'variables' => ['nome' => 'Victor', 'empresa' => 'Acme'],
        ]);

        $this->app->call([new ProcessCommunicationJob($communication->id), 'handle']);

        Mail::assertSent(CommunicationMail::class, fn (CommunicationMail $mail): bool => $mail->subjectLine === 'Bem-vindo Victor'
                && $mail->bodyContent === 'Olá Victor, bem-vindo ao Acme.');

        $this->assertSame(CommunicationStatusEnum::Sent, $communication->fresh()->status);
    }

    public function test_marks_failed_after_exhausting_tries(): void
    {
        $communication = Communication::factory()->sms()->create([
            'message' => 'msg',
        ]);

        $failingFactory = new class extends ChannelSenderFactory
        {
            public function __construct() {}

            public function for(CommunicationChannelEnum $channel): ChannelSenderInterface
            {
                return new class implements ChannelSenderInterface
                {
                    public function send(OutboundMessage $message): array
                    {
                        throw new RuntimeException('falha no provedor');
                    }
                };
            }
        };

        $this->app->instance(ChannelSenderFactory::class, $failingFactory);

        $job = new class($communication->id) extends ProcessCommunicationJob
        {
            public function attempts(): int
            {
                return $this->tries;
            }
        };

        try {
            $this->app->call([$job, 'handle']);
            $this->fail('Expected exception to be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('falha no provedor', $exception->getMessage());
        }

        $communication->refresh();
        $this->assertSame(CommunicationStatusEnum::Failed, $communication->status);
        $this->assertSame('falha no provedor', $communication->failure_reason);

        $this->assertDatabaseHas('communication_logs', [
            'communication_id' => $communication->id,
            'event' => CommunicationLogEventEnum::Failed->value,
        ]);
    }
}
