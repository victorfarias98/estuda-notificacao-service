<?php

namespace App\Services;

use App\Contracts\Repositories\NotificationTemplateRepositoryInterface;
use App\Models\NotificationTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationTemplateService
{
    public function __construct(private readonly NotificationTemplateRepositoryInterface $templates) {}

    /**
     * @param  array{channel?: ?string, is_active?: ?bool, search?: ?string}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->templates->paginate($filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): NotificationTemplate
    {
        return $this->templates->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(NotificationTemplate $template, array $attributes): NotificationTemplate
    {
        return $this->templates->update($template, $attributes);
    }

    public function delete(NotificationTemplate $template): void
    {
        $this->templates->delete($template);
    }
}
