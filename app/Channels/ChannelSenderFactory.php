<?php

namespace App\Channels;

use App\Contracts\ChannelSenderInterface;
use App\Enums\CommunicationChannelEnum;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class ChannelSenderFactory
{
    public function __construct(private readonly Container $container) {}

    public function for(CommunicationChannelEnum $channel): ChannelSenderInterface
    {
        return match ($channel) {
            CommunicationChannelEnum::Email => $this->container->make(EmailChannelSender::class),
            CommunicationChannelEnum::Sms => $this->container->make(SmsChannelSender::class),
            CommunicationChannelEnum::Push => $this->container->make(PushChannelSender::class),
            default => throw new InvalidArgumentException("Canal não suportado: {$channel->value}"),
        };
    }
}
