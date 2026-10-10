<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_reports_api_and_database_status(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'database' => 'connected',
            ]);
    }

    public function test_unknown_api_route_returns_json_404(): void
    {
        $this->get('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_spa_shell_is_served_for_frontend_routes(): void
    {
        $this->withoutVite();

        $this->get('/')->assertOk()->assertSee('id="root"', false);
        $this->get('/shop/some-product')->assertOk()->assertSee('id="root"', false);
    }
}
