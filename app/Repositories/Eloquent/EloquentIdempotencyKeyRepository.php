<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\IdempotencyKeyRepositoryInterface;
use App\Models\IdempotencyKey;

class EloquentIdempotencyKeyRepository implements IdempotencyKeyRepositoryInterface
{
    public function find(string $key): ?IdempotencyKey
    {
        return IdempotencyKey::query()->where('key', $key)->first();
    }

    public function store(array $attributes): IdempotencyKey
    {
        return IdempotencyKey::query()->create($attributes);
    }

    public function pruneExpired(): int
    {
        return IdempotencyKey::query()
            ->where('expires_at', '<', now())
            ->delete();
    }
}
