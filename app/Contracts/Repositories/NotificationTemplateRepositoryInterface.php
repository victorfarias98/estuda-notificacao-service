<?php

namespace App\Contracts\Repositories;

use App\Models\NotificationTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificationTemplateRepositoryInterface
{
    /**
     * @param  array{channel?: ?string, is_active?: ?bool, search?: ?string}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): NotificationTemplate;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(NotificationTemplate $template, array $attributes): NotificationTemplate;

    public function delete(NotificationTemplate $template): void;

    public function findByIdAndChannel(int $id, ?string $channel = null): ?NotificationTemplate;

    public function findBySlugAndChannel(string $slug, ?string $channel = null): ?NotificationTemplate;

    public function existsBySlugForChannel(string $slug, string $channel, ?int $excludingId = null): bool;
}
