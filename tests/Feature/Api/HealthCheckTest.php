<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok_status(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('version', config('app.version'))
            ->assertJsonStructure([
                'status',
                'version',
                'checks' => [
                    'database' => ['status'],
                    'queue' => ['status', 'pending_jobs', 'failed_jobs'],
                ],
            ]);
    }
}
