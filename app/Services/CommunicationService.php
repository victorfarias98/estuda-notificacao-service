<?php

namespace App\Services;

use App\Enums\CommunicationLogEventEnum;
use App\Enums\CommunicationStatusEnum;
use App\Jobs\ProcessCommunicationJob;
use App\Models\Communication;
use App\Models\CommunicationLog;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\DB;

class CommunicationService
{
    /**
     * @param  array{
     *     recipient: string,
     *     channel: string,
     *     subject?: ?string,
     *     message?: ?string,
     *     origin_system: string,
     *     variables?: ?array<string, mixed>
     * }  $payload
     */
    public function createAndDispatch(array $payload, ?NotificationTemplate $template = null): Communication
    {
        return DB::transaction(function () use ($payload, $template): Communication {
            $communication = Communication::query()->create([
                'recipient' => $payload['recipient'],
                'channel' => $payload['channel'],
                'subject' => $payload['subject'] ?? null,
                'message' => $payload['message'] ?? null,
                'origin_system' => $payload['origin_system'],
                'notification_template_id' => $template?->id,
                'variables' => $payload['variables'] ?? null,
                'status' => CommunicationStatusEnum::Pending,
            ]);

            $this->log($communication, CommunicationLogEventEnum::Received, 'Solicitação recebida via API.', [
                'origin_system' => $communication->origin_system,
                'channel' => $communication->channel->value,
                'has_template' => $template !== null,
            ]);

            ProcessCommunicationJob::dispatch($communication->id);

            $communication->forceFill(['queued_at' => now()])->save();

            $this->log($communication, CommunicationLogEventEnum::Queued, 'Job enviado para a fila.', [
                'queue' => config('queue.default'),
            ]);

            return $communication;
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function log(
        Communication $communication,
        CommunicationLogEventEnum $event,
        ?string $message = null,
        array $context = [],
    ): CommunicationLog {
        return CommunicationLog::query()->create([
            'communication_id' => $communication->id,
            'event' => $event,
            'message' => $message,
            'context' => $context !== [] ? $context : null,
        ]);
    }
}
