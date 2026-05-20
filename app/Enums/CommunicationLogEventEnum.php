<?php

namespace App\Enums;

enum CommunicationLogEventEnum: string
{
    case Received = 'received';
    case Queued = 'queued';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
    case Retrying = 'retrying';
    case Retried = 'retried';
    case Cancelled = 'cancelled';
}
