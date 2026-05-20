<?php

namespace Tests\Unit\Channels;

use App\Channels\PushChannelSender;
use App\Channels\SmsChannelSender;
use App\DTOs\OutboundMessage;
use App\Enums\CommunicationChannelEnum;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SmsAndPushChannelSenderTest extends TestCase
{
    public function test_sms_sender_logs_message(): void
    {
        /** @var MockInterface $logChannel */
        $logChannel = Mockery::mock();
        $logChannel->shouldReceive('info')->once()->withArgs(function (string $msg, array $context): bool {
            return str_contains($msg, '[SMS]')
                && $context['communication_id'] === 99
                && $context['recipient'] === '+5511999999999';
        });

        Log::shouldReceive('channel')->andReturn($logChannel);

        $result = (new SmsChannelSender)->send(new OutboundMessage(
            channel: CommunicationChannelEnum::Sms,
            recipient: '+5511999999999',
            subject: null,
            body: 'oi',
            originSystem: 'sistema',
            communicationId: 99,
        ));

        $this->assertSame('sms-log', $result['driver']);
    }

    public function test_push_sender_logs_message(): void
    {
        /** @var MockInterface $logChannel */
        $logChannel = Mockery::mock();
        $logChannel->shouldReceive('info')->once()->withArgs(function (string $msg, array $context): bool {
            return str_contains($msg, '[PUSH]')
                && $context['device_token'] === 'token-xyz';
        });

        Log::shouldReceive('channel')->andReturn($logChannel);

        $result = (new PushChannelSender)->send(new OutboundMessage(
            channel: CommunicationChannelEnum::Push,
            recipient: 'token-xyz',
            subject: 'titulo',
            body: 'corpo',
            originSystem: 'sistema',
            communicationId: 1,
        ));

        $this->assertSame('push-log', $result['driver']);
    }
}
