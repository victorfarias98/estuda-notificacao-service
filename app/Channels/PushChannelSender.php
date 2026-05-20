<?php

namespace App\Channels;

use App\Contracts\ChannelSenderInterface;
use App\DTOs\OutboundMessage;
use Illuminate\Support\Facades\Log;

class PushChannelSender implements ChannelSenderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function send(OutboundMessage $message): array
    {
        Log::channel(config('logging.default'))->info('[PUSH] notificação enviada (driver fake)', [
            'communication_id' => $message->communicationId,
            'device_token' => $message->recipient,
            'origin_system' => $message->originSystem,
            'title' => $message->subject,
        ]);

        return [
            'driver' => 'push-log',
            'device_token' => $message->recipient,
        ];
    }
}
