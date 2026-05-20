<?php

use App\Exceptions\CommunicationNotEditableException;
use App\Models\Communication;
use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (CommunicationNotEditableException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'error' => 'communication_not_editable',
                'status' => $exception->currentStatus()->value,
            ], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = match ($exception->getModel()) {
                Communication::class => 'Comunicação não encontrada.',
                NotificationTemplate::class => 'Template de notificação não encontrado.',
                default => 'Recurso não encontrado.',
            };

            return response()->json([
                'message' => $message,
                'error' => 'not_found',
            ], Response::HTTP_NOT_FOUND);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $previous = $exception->getPrevious();

            if ($previous instanceof ModelNotFoundException) {
                $message = match ($previous->getModel()) {
                    Communication::class => 'Comunicação não encontrada.',
                    NotificationTemplate::class => 'Template de notificação não encontrado.',
                    default => 'Recurso não encontrado.',
                };

                return response()->json([
                    'message' => $message,
                    'error' => 'not_found',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'message' => 'Rota não encontrada.',
                'error' => 'route_not_found',
            ], Response::HTTP_NOT_FOUND);
        });
    })->create();
