<?php

namespace App\Models;

use App\Enums\CommunicationLogEventEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['communication_id', 'event', 'message', 'context'])]
class CommunicationLog extends Model
{
    public $timestamps = false;

    protected $attributes = [
        'created_at' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => CommunicationLogEventEnum::class,
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Communication, $this>
     */
    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class);
    }

    protected static function booted(): void
    {
        static::creating(function (CommunicationLog $log): void {
            if ($log->created_at === null) {
                $log->created_at = now();
            }
        });
    }
}
