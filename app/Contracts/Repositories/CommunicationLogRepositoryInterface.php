<?php

namespace App\Contracts\Repositories;

use App\Enums\CommunicationLogEventEnum;
use App\Models\Communication;
use App\Models\CommunicationLog;

interface CommunicationLogRepositoryInterface
{
    /**
     * @param  array<string, mixed>|null  $context
     */
    public function record(
        Communication $communication,
        CommunicationLogEventEnum $event,
        ?string $message = null,
        ?array $context = null,
    ): CommunicationLog;
}
