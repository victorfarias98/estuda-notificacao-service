<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\NotificationTemplateRepositoryInterface;
use App\Models\NotificationTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentNotificationTemplateRepository implements NotificationTemplateRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return NotificationTemplate::query()
            ->when($filters['channel'] ?? null, fn (Builder $query, string $channel): Builder => $query->where('channel', $channel))
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn (Builder $query): Builder => $query->where('is_active', $filters['is_active'])
            )
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate($perPage);
    }

    public function create(array $attributes): NotificationTemplate
    {
        return NotificationTemplate::query()->create($attributes);
    }

    public function update(NotificationTemplate $template, array $attributes): NotificationTemplate
    {
        $template->fill($attributes)->save();

        return $template->refresh();
    }

    public function delete(NotificationTemplate $template): void
    {
        $template->delete();
    }

    public function findByIdAndChannel(int $id, ?string $channel = null): ?NotificationTemplate
    {
        return NotificationTemplate::query()
            ->when($channel, fn (Builder $query, string $channel): Builder => $query->where('channel', $channel))
            ->find($id);
    }

    public function findBySlugAndChannel(string $slug, ?string $channel = null): ?NotificationTemplate
    {
        return NotificationTemplate::query()
            ->where('slug', $slug)
            ->when($channel, fn (Builder $query, string $channel): Builder => $query->where('channel', $channel))
            ->first();
    }

    public function existsBySlugForChannel(string $slug, string $channel, ?int $excludingId = null): bool
    {
        return NotificationTemplate::query()
            ->where('slug', $slug)
            ->where('channel', $channel)
            ->when($excludingId, fn (Builder $query, int $id): Builder => $query->whereKeyNot($id))
            ->exists();
    }
}
