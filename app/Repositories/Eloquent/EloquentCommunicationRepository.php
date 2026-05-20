<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\CommunicationRepositoryInterface;
use App\Enums\CommunicationStatusEnum;
use App\Models\Communication;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentCommunicationRepository implements CommunicationRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $includeCancelled = filter_var($filters['include_cancelled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $this->baseQuery($includeCancelled)
            ->with('template:id,slug,channel')
            ->when($filters['channel'] ?? null, fn (Builder $query, string $channel): Builder => $query->where('channel', $channel))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['origin_system'] ?? null, fn (Builder $query, string $origin): Builder => $query->where('origin_system', $origin))
            ->when($filters['recipient'] ?? null, fn (Builder $query, string $recipient): Builder => $query->where('recipient', 'like', "%{$recipient}%"))
            ->when($filters['template_id'] ?? null, fn (Builder $query, $templateId): Builder => $query->where('notification_template_id', $templateId))
            ->when($filters['template_slug'] ?? null, function (Builder $query, string $slug): void {
                $query->whereHas('template', fn (Builder $templateQuery): Builder => $templateQuery->where('slug', $slug));
            })
            ->latest('id')
            ->paginate($perPage);
    }

    public function create(array $attributes): Communication
    {
        return Communication::query()->create($attributes);
    }

    public function update(Communication $communication, array $attributes): Communication
    {
        $communication->fill($attributes)->save();

        return $communication->refresh();
    }

    public function delete(Communication $communication): void
    {
        $communication->delete();
    }

    public function find(int $id): ?Communication
    {
        return $this->baseQuery()->find($id);
    }

    public function findForProcessing(int $id): ?Communication
    {
        return $this->baseQuery()->with('template')->find($id);
    }

    public function findIncludingTrashed(int $id): ?Communication
    {
        return Communication::withTrashed()
            ->with(['template', 'logs'])
            ->find($id);
    }

    /**
     * @return Builder<Communication>
     */
    private function baseQuery(bool $includeCancelled = false): Builder
    {
        $query = Communication::query();

        if ($includeCancelled) {
            return $query->withTrashed();
        }

        return $query->where('status', '!=', CommunicationStatusEnum::Cancelled->value);
    }
}
