<?php

namespace App\Models;

use App\Enums\CommunicationChannelEnum;
use App\Enums\CommunicationStatusEnum;
use Database\Factories\CommunicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'recipient',
    'channel',
    'subject',
    'message',
    'origin_system',
    'notification_template_id',
    'variables',
    'status',
    'failure_reason',
    'queued_at',
    'processed_at',
    'sent_at',
    'attempts',
])]
class Communication extends Model
{
    /** @use HasFactory<CommunicationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => CommunicationChannelEnum::class,
            'status' => CommunicationStatusEnum::class,
            'variables' => 'array',
            'queued_at' => 'datetime',
            'processed_at' => 'datetime',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<NotificationTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    /**
     * @return HasMany<CommunicationLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(CommunicationLog::class)->orderBy('id');
    }

    public function markProcessing(): void
    {
        $this->forceFill([
            'status' => CommunicationStatusEnum::Processing,
            'processed_at' => now(),
            'attempts' => $this->attempts + 1,
        ])->save();
    }

    public function markSent(): void
    {
        $this->forceFill([
            'status' => CommunicationStatusEnum::Sent,
            'sent_at' => now(),
            'failure_reason' => null,
        ])->save();
    }

    public function markFailed(string $reason): void
    {
        $this->forceFill([
            'status' => CommunicationStatusEnum::Failed,
            'failure_reason' => $reason,
        ])->save();
    }
}
