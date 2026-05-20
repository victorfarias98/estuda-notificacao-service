<?php

namespace App\Jobs;

use App\Channels\ChannelSenderFactory;
use App\Enums\CommunicationLogEventEnum;
use App\Models\Communication;
use App\Services\CommunicationService;
use App\Services\TemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessCommunicationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public readonly int $communicationId) {}

    public function handle(
        CommunicationService $service,
        TemplateRenderer $renderer,
        ChannelSenderFactory $factory,
    ): void {
        /** @var Communication|null $communication */
        $communication = Communication::query()->with('template')->find($this->communicationId);

        if ($communication === null) {
            return;
        }

        $communication->markProcessing();
        $service->log($communication, CommunicationLogEventEnum::Processing, 'Iniciando processamento.', [
            'attempt' => $communication->attempts,
        ]);

        try {
            [$subject, $body] = $this->resolveContent($communication, $renderer);

            $sender = $factory->for($communication->channel);
            $result = $sender->send(\App\DTOs\OutboundMessage::fromCommunication($communication, $subject, $body));

            $communication->markSent();
            $service->log($communication, CommunicationLogEventEnum::Sent, 'Mensagem enviada com sucesso.', $result);
        } catch (Throwable $exception) {
            $service->log($communication, CommunicationLogEventEnum::Failed, $exception->getMessage(), [
                'exception' => $exception::class,
                'attempt' => $communication->attempts,
            ]);

            if ($this->attempts() >= $this->tries) {
                $communication->markFailed($exception->getMessage());
            }

            throw $exception;
        }
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function resolveContent(Communication $communication, TemplateRenderer $renderer): array
    {
        if ($communication->template !== null) {
            $rendered = $renderer->render($communication->template, $communication->variables);

            return [
                $rendered['subject'] ?? $communication->subject,
                $rendered['body'],
            ];
        }

        return [$communication->subject, (string) $communication->message];
    }

    public function failed(Throwable $exception): void
    {
        /** @var Communication|null $communication */
        $communication = Communication::query()->find($this->communicationId);

        if ($communication === null) {
            return;
        }

        $communication->markFailed($exception->getMessage());

        app(CommunicationService::class)->log(
            $communication,
            CommunicationLogEventEnum::Failed,
            'Job esgotou as tentativas.',
            ['exception' => $exception::class, 'message' => $exception->getMessage()],
        );
    }
}
