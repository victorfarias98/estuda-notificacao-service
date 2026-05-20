<?php

namespace App\Http\Middleware;

use App\Contracts\Repositories\IdempotencyKeyRepositoryInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleIdempotencyKey
{
    public function __construct(private readonly IdempotencyKeyRepositoryInterface $idempotencyKeys) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '' || ! $this->isStoreCommunication($request)) {
            return $next($request);
        }

        $requestHash = hash('sha256', $request->getContent());
        $existing = $this->idempotencyKeys->find($key);

        if ($existing !== null) {
            if ($existing->request_hash !== $requestHash) {
                return response()->json([
                    'message' => 'A chave de idempotência já foi utilizada com um corpo de requisição diferente.',
                    'error' => 'idempotency_conflict',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response()->json(
                $existing->response_body,
                $existing->response_status,
            );
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $body = json_decode($response->getContent(), true);

            if (is_array($body)) {
                $this->idempotencyKeys->store([
                    'key' => $key,
                    'origin_system' => $request->input('origin_system'),
                    'request_hash' => $requestHash,
                    'communication_id' => data_get($body, 'data.id'),
                    'response_status' => $response->getStatusCode(),
                    'response_body' => $body,
                    'expires_at' => now()->addHours((int) config('notifications.idempotency_ttl_hours', 24)),
                ]);
            }
        }

        return $response;
    }

    private function isStoreCommunication(Request $request): bool
    {
        return $request->isMethod('POST')
            && $request->is('api/v1/communications');
    }
}
