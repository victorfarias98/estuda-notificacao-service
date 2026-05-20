<?php

namespace Tests\Unit\Channels;

use App\Channels\ChannelSenderFactory;
use App\Channels\EmailChannelSender;
use App\Channels\PushChannelSender;
use App\Channels\SmsChannelSender;
use App\Enums\CommunicationChannelEnum;
use Tests\TestCase;

class ChannelSenderFactoryTest extends TestCase
{
    public function test_resolves_correct_sender_per_channel(): void
    {
        $factory = $this->app->make(ChannelSenderFactory::class);

        $this->assertInstanceOf(EmailChannelSender::class, $factory->for(CommunicationChannelEnum::Email));
        $this->assertInstanceOf(SmsChannelSender::class, $factory->for(CommunicationChannelEnum::Sms));
        $this->assertInstanceOf(PushChannelSender::class, $factory->for(CommunicationChannelEnum::Push));
    }
}
