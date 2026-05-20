<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\CommunicationLogRepositoryInterface;
use App\Enums\CommunicationLogEventEnum;
use App\Models\Communication;
use App\Models\CommunicationLog;

class EloquentCommunicationLogRepository implements CommunicationLogRepositoryInterface
{
    public function record(
        Communication $communication,
        CommunicationLogEventEnum $event,
        ?string $message = null,
        ?array $context = null,
    ): CommunicationLog {
        return CommunicationLog::query()->create([
            'communication_id' => $communication->id,
            'event' => $event,
            'message' => $message,
            'context' => $context !== null && $context !== [] ? $context : null,
        ]);
    }
}
