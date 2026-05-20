<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyKey extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'origin_system',
        'request_hash',
        'communication_id',
        'response_status',
        'response_body',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'expires_at' => 'datetime',
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
}
