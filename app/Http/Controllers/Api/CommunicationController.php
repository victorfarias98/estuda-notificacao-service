<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommunicationRequest;
use App\Http\Resources\CommunicationResource;
use App\Models\Communication;
use App\Services\CommunicationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CommunicationController extends Controller
{
    public function __construct(private readonly CommunicationService $communications) {}

    public function store(StoreCommunicationRequest $request): JsonResponse
    {
        $template = $request->resolveTemplate();

        $communication = $this->communications->createAndDispatch(
            payload: $request->safe()->except(['template_id', 'template_slug']),
            template: $template,
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
}
