<?php

namespace App\Services;

use App\Enums\CommunicationLogEventEnum;
use App\Enums\CommunicationStatusEnum;
use App\Exceptions\CommunicationNotEditableException;
use App\Jobs\ProcessCommunicationJob;
use App\Models\Communication;
use App\Models\CommunicationLog;
use App\Models\NotificationTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommunicationService
{
    /**
     * @param  array{
     *     channel?: ?string,
     *     status?: ?string,
     *     origin_system?: ?string,
     *     recipient?: ?string,
     *     template_id?: ?int|string,
     *     template_slug?: ?string,
     * }  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Communication::query()
            ->with('template:id,slug,channel')
            ->when($filters['channel'] ?? null, fn ($query, string $channel) => $query->where('channel', $channel))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['origin_system'] ?? null, fn ($query, string $origin) => $query->where('origin_system', $origin))
            ->when($filters['recipient'] ?? null, fn ($query, string $recipient) => $query->where('recipient', 'like', "%{$recipient}%"))
            ->when($filters['template_id'] ?? null, fn ($query, $templateId) => $query->where('notification_template_id', $templateId))
            ->when($filters['template_slug'] ?? null, function ($query, string $slug): void {
                $query->whereHas('template', fn ($templateQuery) => $templateQuery->where('slug', $slug));
            })
            ->orderByDesc('id')
            ->paginate($perPage);
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

            $communication->fill($changes)->save();

            $this->log($communication, CommunicationLogEventEnum::Received, 'Comunicação atualizada antes do envio.', [
                'changed_fields' => array_keys($changes),
            ]);

            return $communication->refresh();
        });
    }

    public function delete(Communication $communication): void
    {
        $this->ensureEditable($communication, action: 'excluída');

        $this->log($communication, CommunicationLogEventEnum::Failed, 'Comunicação excluída antes do envio.', [
            'deleted_by' => 'api',
        ]);

        $communication->delete();
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
        $logEntry = CommunicationLog::query()->create([
            'communication_id' => $communication->id,
            'event' => $event,
            'message' => $message,
            'context' => $context !== [] ? $context : null,
        ]);

        $logger = $event === CommunicationLogEventEnum::Failed ? 'error' : 'info';

        Log::{$logger}(sprintf(
            '[communication#%d][%s][%s] %s',
            $communication->id,
            strtoupper($communication->channel->value),
            strtoupper($event->value),
            $message ?? '',
        ), [
            'recipient' => $communication->recipient,
            'origin_system' => $communication->origin_system,
            ...$context,
        ]);

        return $logEntry;
    }
}
