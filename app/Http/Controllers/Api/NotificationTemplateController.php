<?php

namespace App\Http\Controllers\Api;

use App\Enums\CommunicationChannelEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNotificationTemplateRequest;
use App\Http\Requests\UpdateNotificationTemplateRequest;
use App\Http\Resources\NotificationTemplateResource;
use App\Models\NotificationTemplate;
use App\Services\NotificationTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class NotificationTemplateController extends Controller
{
    public function __construct(private readonly NotificationTemplateService $templates) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'channel' => ['nullable', 'string', Rule::in(CommunicationChannelEnum::values())],
            'is_active' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->templates->paginate(
            filters: $filters,
            perPage: (int) ($filters['per_page'] ?? 15),
        );

        return NotificationTemplateResource::collection($paginator);
    }

    public function store(StoreNotificationTemplateRequest $request): JsonResponse
    {
        $template = $this->templates->create($request->validated());

        return NotificationTemplateResource::make($template)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(NotificationTemplate $notificationTemplate): NotificationTemplateResource
    {
        return NotificationTemplateResource::make($notificationTemplate);
    }

    public function update(
        UpdateNotificationTemplateRequest $request,
        NotificationTemplate $notificationTemplate,
    ): NotificationTemplateResource {
        $updated = $this->templates->update($notificationTemplate, $request->validated());

        return NotificationTemplateResource::make($updated);
    }

    public function destroy(NotificationTemplate $notificationTemplate): JsonResponse
    {
        $this->templates->delete($notificationTemplate);

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
