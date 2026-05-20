<?php

namespace App\Contracts;

use App\DTOs\OutboundMessage;

interface ChannelSenderInterface
{
    /**
     * Envia a mensagem pelo canal específico.
     *
     * @return array<string, mixed> Detalhes do envio (driver, response, etc.) para logs.
     */
    public function send(OutboundMessage $message): array;
}
