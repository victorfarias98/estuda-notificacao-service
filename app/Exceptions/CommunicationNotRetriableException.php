<?php

namespace App\Exceptions;

use App\Enums\CommunicationStatusEnum;
use App\Models\Communication;
use RuntimeException;

class CommunicationNotRetriableException extends RuntimeException
{
    public function __construct(public readonly Communication $communication)
    {
        parent::__construct(sprintf(
            'A comunicação #%d não pode ser reenviada no estado atual (%s). Apenas comunicações com status failed podem ser reprocessadas.',
            $communication->id,
            $communication->status->value,
        ));
    }

    public function currentStatus(): CommunicationStatusEnum
    {
        return $this->communication->status;
    }
}
