<?php

namespace App\Http\Controllers\Api;

use App\Enums\CommunicationChannelEnum;
use App\Enums\CommunicationStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommunicationRequest;
use App\Http\Requests\UpdateCommunicationRequest;
use App\Http\Resources\CommunicationResource;
use App\Models\Communication;
use App\Services\CommunicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class CommunicationController extends Controller
{
    public function __construct(private readonly CommunicationService $communications) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'channel' => ['nullable', 'string', Rule::in(CommunicationChannelEnum::values())],
            'status' => ['nullable', 'string', Rule::in(CommunicationStatusEnum::values())],
            'origin_system' => ['nullable', 'string', 'max:120'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'template_id' => ['nullable', 'integer'],
            'template_slug' => ['nullable', 'string', 'max:255'],
            'include_cancelled' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->communications->paginate(
            filters: $filters,
            perPage: (int) ($filters['per_page'] ?? 15),
        );

        return CommunicationResource::collection($paginator);
    }

    public function store(StoreCommunicationRequest $request): JsonResponse
    {
        $template = $request->resolveTemplate();

        $communication = $this->communications->createAndDispatch(
            payload: $request->safe()->except(['template_id', 'template_slug']),
            template: $template,
            correlationId: $request->attributes->get('correlation_id'),
        );

        return CommunicationResource::make($communication->fresh(['template']))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function show(Communication $communication): CommunicationResource
    {
        $communication->load(['template', 'logs']);

        return CommunicationResource::make($communication);
    }

    public function update(UpdateCommunicationRequest $request, Communication $communication): CommunicationResource
    {
        $template = $request->resolveTemplate($communication);

        $updated = $this->communications->update(
            communication: $communication,
            attributes: $request->safe()->except(['template_id', 'template_slug']),
            template: $template,
            clearTemplate: $request->wantsToClearTemplate(),
        );

        return CommunicationResource::make($updated->load('template'));
    }

    public function retry(Communication $communication): JsonResponse
    {
        $retried = $this->communications->retry($communication);

        return CommunicationResource::make($retried->load('template'))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Communication $communication): JsonResponse
    {
        $this->communications->delete($communication);

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
