<?php

namespace App\Models;

use App\Enums\CommunicationChannelEnum;
use Database\Factories\NotificationTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'channel', 'subject', 'body', 'description', 'is_active'])]
class NotificationTemplate extends Model
{
    /** @use HasFactory<NotificationTemplateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => CommunicationChannelEnum::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Communication, $this>
     */
    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class);
    }
}
