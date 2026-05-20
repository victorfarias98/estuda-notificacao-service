<?php

namespace App\Exceptions;

use App\Enums\CommunicationStatusEnum;
use App\Models\Communication;
use RuntimeException;

class CommunicationNotEditableException extends RuntimeException
{
    public function __construct(public readonly Communication $communication, public readonly string $action)
    {
        parent::__construct(sprintf(
            'A comunicação #%d não pode ser %s no estado atual (%s).',
            $communication->id,
            $action,
            $communication->status->value,
        ));
    }

    public function currentStatus(): CommunicationStatusEnum
    {
        return $this->communication->status;
    }
}
