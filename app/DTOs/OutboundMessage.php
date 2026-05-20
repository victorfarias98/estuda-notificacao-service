<?php

namespace App\DTOs;

use App\Enums\CommunicationChannelEnum;
use App\Models\Communication;

class OutboundMessage
{
    public function __construct(
        public readonly CommunicationChannelEnum $channel,
        public readonly string $recipient,
        public readonly ?string $subject,
        public readonly string $body,
        public readonly string $originSystem,
        public readonly int $communicationId,
    ) {}

    public static function fromCommunication(Communication $communication, ?string $subject, string $body): self
    {
        return new self(
            channel: $communication->channel,
            recipient: $communication->recipient,
            subject: $subject,
            body: $body,
            originSystem: $communication->origin_system,
            communicationId: $communication->id,
        );
    }
}
