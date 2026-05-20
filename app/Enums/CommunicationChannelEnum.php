<?php

namespace App\Enums;

enum CommunicationChannelEnum: string
{
    case Email = 'email';
    case Sms = 'sms';
    case Push = 'push';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
