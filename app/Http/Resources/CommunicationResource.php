<?php

namespace App\Http\Resources;

use App\Models\Communication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Communication
 */
class CommunicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recipient' => $this->recipient,
            'channel' => $this->channel->value,
            'subject' => $this->subject,
            'message' => $this->message,
            'origin_system' => $this->origin_system,
            'status' => $this->status->value,
            'failure_reason' => $this->failure_reason,
            'attempts' => $this->attempts,
            'template' => $this->whenLoaded('template', fn () => $this->template ? [
                'id' => $this->template->id,
                'slug' => $this->template->slug,
                'channel' => $this->template->channel->value,
            ] : null),
            'variables' => $this->variables,
            'queued_at' => $this->queued_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'logs' => CommunicationLogResource::collection($this->whenLoaded('logs')),
        ];
    }
}
