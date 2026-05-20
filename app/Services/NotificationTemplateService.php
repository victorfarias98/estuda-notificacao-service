<?php

namespace App\Services;

use App\Models\NotificationTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationTemplateService
{
    /**
     * @param  array{channel?: ?string, is_active?: ?bool, search?: ?string}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return NotificationTemplate::query()
            ->when($filters['channel'] ?? null, fn ($query, string $channel) => $query->where('channel', $channel))
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', $filters['is_active']))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): NotificationTemplate
    {
        return NotificationTemplate::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(NotificationTemplate $template, array $attributes): NotificationTemplate
    {
        $template->fill($attributes)->save();

        return $template->refresh();
    }

    public function delete(NotificationTemplate $template): void
    {
        $template->delete();
    }
}
