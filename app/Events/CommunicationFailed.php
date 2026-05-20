<?php

namespace App\Events;

use App\Models\Communication;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommunicationFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Communication $communication) {}
}
