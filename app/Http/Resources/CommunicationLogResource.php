<?php

namespace App\Http\Resources;

use App\Models\CommunicationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CommunicationLog
 */
class CommunicationLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event->value,
            'message' => $this->message,
            'context' => $this->context,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
