<?php

namespace App\Contracts\Repositories;

use App\Models\IdempotencyKey;

interface IdempotencyKeyRepositoryInterface
{
    public function find(string $key): ?IdempotencyKey;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function store(array $attributes): IdempotencyKey;

    public function pruneExpired(): int;
}
