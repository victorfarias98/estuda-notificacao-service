<?php

namespace App\Contracts\Repositories;

use App\Models\Communication;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CommunicationRepositoryInterface
{
    /**
     * @param  array{
     *     channel?: ?string,
     *     status?: ?string,
     *     origin_system?: ?string,
     *     recipient?: ?string,
     *     template_id?: ?int|string,
     *     template_slug?: ?string,
     *     include_cancelled?: ?bool,
     * }  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Communication;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Communication $communication, array $attributes): Communication;

    public function delete(Communication $communication): void;

    public function find(int $id): ?Communication;

    public function findForProcessing(int $id): ?Communication;

    public function findIncludingTrashed(int $id): ?Communication;
}
