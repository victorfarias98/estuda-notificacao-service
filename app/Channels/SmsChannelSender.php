<?php

namespace App\Channels;

use App\Contracts\ChannelSenderInterface;
use App\DTOs\OutboundMessage;
use Illuminate\Support\Facades\Log;

class SmsChannelSender implements ChannelSenderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function send(OutboundMessage $message): array
    {
        Log::channel(config('logging.default'))->info('[SMS] mensagem enviada (driver fake)', [
            'communication_id' => $message->communicationId,
            'recipient' => $message->recipient,
            'origin_system' => $message->originSystem,
            'body_preview' => mb_substr($message->body, 0, 80),
        ]);

        return [
            'driver' => 'sms-log',
            'recipient' => $message->recipient,
        ];
    }
}
