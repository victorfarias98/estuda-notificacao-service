<?php

namespace App\Services;

use App\Contracts\Repositories\CommunicationLogRepositoryInterface;
use App\Contracts\Repositories\CommunicationRepositoryInterface;
use App\Enums\CommunicationLogEventEnum;
use App\Enums\CommunicationStatusEnum;
use App\Events\CommunicationCreated;
use App\Exceptions\CommunicationNotEditableException;
use App\Exceptions\CommunicationNotRetriableException;
use App\Jobs\ProcessCommunicationJob;
use App\Models\Communication;
use App\Models\CommunicationLog;
use App\Models\NotificationTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommunicationService
{
    public function __construct(
        private readonly CommunicationRepositoryInterface $communications,
        private readonly CommunicationLogRepositoryInterface $logs,
    ) {}

    /**
     * @param  array{
     *     channel?: ?string,
     *     status?: ?string,
     *     origin_system?: ?string,
     *     recipient?: ?string,
     *     template_id?: ?int|string,
     *     template_slug?: ?string,
     *     include_cancelled?: ?bool,
     * }  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->communications->paginate($filters, $perPage);
    }

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
    public function createAndDispatch(
        array $payload,
        ?NotificationTemplate $template = null,
        ?string $correlationId = null,
    ): Communication {
        return DB::transaction(function () use ($payload, $template, $correlationId): Communication {
            $communication = $this->communications->create([
                'recipient' => $payload['recipient'],
                'channel' => $payload['channel'],
                'subject' => $payload['subject'] ?? null,
                'message' => $payload['message'] ?? null,
                'origin_system' => $payload['origin_system'],
                'correlation_id' => $correlationId,
                'notification_template_id' => $template?->id,
                'variables' => $payload['variables'] ?? null,
                'status' => CommunicationStatusEnum::Pending,
                'queued_at' => now(),
            ]);

            $this->log($communication, CommunicationLogEventEnum::Received, 'Solicitação recebida via API.', [
                'origin_system' => $communication->origin_system,
                'channel' => $communication->channel->value,
                'has_template' => $template !== null,
            ]);

            ProcessCommunicationJob::dispatch($communication->id);

            $this->log($communication, CommunicationLogEventEnum::Queued, 'Job enviado para a fila.', [
                'queue' => config('queue.default'),
            ]);

            CommunicationCreated::dispatch($communication);

            return $communication;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Communication $communication, array $attributes, ?NotificationTemplate $template = null, bool $clearTemplate = false): Communication
    {
        $this->ensureEditable($communication, action: 'atualizada');

        return DB::transaction(function () use ($communication, $attributes, $template, $clearTemplate): Communication {
            $changes = collect($attributes)
                ->only(['recipient', 'subject', 'message', 'origin_system', 'variables'])
                ->all();

            if ($template !== null) {
                $changes['notification_template_id'] = $template->id;
            } elseif ($clearTemplate) {
                $changes['notification_template_id'] = null;
            }

            $updated = $this->communications->update($communication, $changes);

            $this->log($updated, CommunicationLogEventEnum::Received, 'Comunicação atualizada antes do envio.', [
                'changed_fields' => array_keys($changes),
            ]);

            return $updated;
        });
    }

    public function retry(Communication $communication): Communication
    {
        if ($communication->status !== CommunicationStatusEnum::Failed) {
            throw new CommunicationNotRetriableException($communication);
        }

        return DB::transaction(function () use ($communication): Communication {
            $updated = $this->communications->update($communication, [
                'status' => CommunicationStatusEnum::Pending,
                'failure_reason' => null,
                'queued_at' => now(),
            ]);

            ProcessCommunicationJob::dispatch($updated->id);

            $this->log($updated, CommunicationLogEventEnum::Retried, 'Reprocessamento solicitado via API.');

            return $updated;
        });
    }

    public function delete(Communication $communication): void
    {
        $this->ensureEditable($communication, action: 'cancelada');

        DB::transaction(function () use ($communication): void {
            $communication->markCancelled();

            $this->log($communication, CommunicationLogEventEnum::Cancelled, 'Comunicação cancelada antes do envio.', [
                'deleted_by' => 'api',
            ]);

            $communication->delete();
        });
    }

    public function ensureEditable(Communication $communication, string $action): void
    {
        if ($communication->status !== CommunicationStatusEnum::Pending) {
            throw new CommunicationNotEditableException($communication, $action);
        }
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
        $logEntry = $this->logs->record($communication, $event, $message, $context !== [] ? $context : null);

        $logger = in_array($event, [CommunicationLogEventEnum::Failed], true) ? 'error' : 'info';

        Log::{$logger}(sprintf(
            '[communication#%d][%s][%s] %s',
            $communication->id,
            strtoupper($communication->channel->value),
            strtoupper($event->value),
            $message ?? '',
        ), array_filter([
            'correlation_id' => $communication->correlation_id,
            'recipient' => $communication->recipient,
            'origin_system' => $communication->origin_system,
            ...$context,
        ]));

        return $logEntry;
    }
}
