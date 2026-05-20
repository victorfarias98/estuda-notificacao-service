<?php

namespace App\Channels;

use App\Contracts\ChannelSenderInterface;
use App\DTOs\OutboundMessage;
use App\Mail\CommunicationMail;
use Illuminate\Support\Facades\Mail;

class EmailChannelSender implements ChannelSenderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function send(OutboundMessage $message): array
    {
        $subject = $message->subject ?? config('app.name');

        Mail::to($message->recipient)->send(new CommunicationMail(
            subjectLine: $subject,
            bodyContent: $message->body,
        ));

        return [
            'driver' => 'mail',
            'mailer' => config('mail.default'),
            'recipient' => $message->recipient,
            'subject' => $subject,
        ];
    }
}
